<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTrainingApplicationRequest;
use App\Models\TrainingOpportunity;
use App\Services\TrainingOpportunityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * بوابة التدريب التعاوني — الصفحة العامة (بلا Auth، مطابق لـCommunityController):
 * أي زائر يتصفح فرص تدريب مفتوحة. التقديم وحده يتطلب تسجيل دخول (auth middleware
 * على مستوى الـRoute)، بعكس استفسارات مجتمع الخدمات المفتوحة للضيوف.
 */
class TrainingController extends Controller
{
    public function index(Request $request): View
    {
        $query = TrainingOpportunity::query()->with('user')->open()->latest();

        if ($category = $request->string('category')->toString()) {
            $query->where('category', $category);
        }

        $opportunities = $query->paginate(12)->withQueryString();

        return view('internships.index', compact('opportunities'));
    }

    public function show(TrainingOpportunity $opportunity): View
    {
        abort_unless($opportunity->isOpen(), 404);
        $opportunity->load('user');

        $isOwner = Auth::check() && $opportunity->user_id === Auth::id();
        $alreadyApplied = Auth::check() && ! $isOwner
            && $opportunity->applications()->where('user_id', Auth::id())->exists();

        return view('internships.show', compact('opportunity', 'isOwner', 'alreadyApplied'));
    }

    public function apply(StoreTrainingApplicationRequest $request, TrainingOpportunity $opportunity, TrainingOpportunityService $service): RedirectResponse
    {
        $documents = [
            'cv' => $request->file('cv'),
            'enrollment_letter' => $request->file('enrollment_letter'),
            'transcript' => $request->file('transcript'),
            'national_id' => $request->file('national_id'),
        ];

        try {
            $service->submitApplication(Auth::user(), $opportunity, $request->input('message'), $documents);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['application' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'أُرسِل تقديمك بنجاح — المستندات وصلت لصاحب الفرصة.');
    }
}
