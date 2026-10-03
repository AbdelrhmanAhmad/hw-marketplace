<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTrainingOpportunityRequest;
use App\Models\TrainingApplication;
use App\Models\TrainingApplicationDocument;
use App\Models\TrainingOpportunity;
use App\Services\TrainingOpportunityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * لوحة "فرصي" — نشر/إغلاق فرص تدريب (لا مراجعة إدارية، مطابق تمامًا للوحة
 * "إعلاناتي" بمجتمع الخدمات). يفترض عبور marketplace.entitled:internships
 * أولًا. التصفح العام منفصل تمامًا (TrainingController، بلا Auth).
 */
class TrainingOpportunityDashboardController extends Controller
{
    public function index(): View
    {
        $opportunities = TrainingOpportunity::where('user_id', Auth::id())->latest()->paginate(10);

        return view('internships.dashboard.index', compact('opportunities'));
    }

    public function create(): View
    {
        return view('internships.dashboard.create');
    }

    public function store(StoreTrainingOpportunityRequest $request, TrainingOpportunityService $service): RedirectResponse
    {
        try {
            $opportunity = $service->createOpportunity(Auth::user(), $request->validated());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['title' => $e->getMessage()])->withInput();
        }

        return redirect()->route('internships.dashboard.show', $opportunity)->with('status', 'نُشرت فرصة التدريب بنجاح.');
    }

    public function show(TrainingOpportunity $opportunity): View
    {
        Gate::authorize('view', $opportunity);

        return view('internships.dashboard.show', [
            'opportunity' => $opportunity,
            'applications' => $opportunity->applications()->with(['user', 'documents'])->latest()->get(),
        ]);
    }

    public function close(TrainingOpportunity $opportunity, TrainingOpportunityService $service): RedirectResponse
    {
        try {
            $service->closeOpportunity(Auth::user(), $opportunity);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', 'أُغلِقت فرصة التدريب.');
    }

    /**
     * لا رابط عام مباشر للمستند (القرص local ليس Public) — كل تنزيل يمر من
     * هنا، بعد فحص TrainingOpportunityPolicy::view صراحة على الفرصة المالكة
     * (نفس نمط CaseDocumentController::download تمامًا).
     */
    public function downloadDocument(TrainingOpportunity $opportunity, TrainingApplication $application, TrainingApplicationDocument $document): StreamedResponse
    {
        abort_unless($application->training_opportunity_id === $opportunity->id, 404);
        abort_unless($document->training_application_id === $application->id, 404);
        Gate::authorize('view', $opportunity);

        return $document->download();
    }
}
