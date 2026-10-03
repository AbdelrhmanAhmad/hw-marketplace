<?php

namespace App\Http\Controllers\BankruptcyTech;

use App\Http\Controllers\BankruptcyTech\Concerns\RedirectsToCaseTab;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBankruptcyCaseRequest;
use App\Models\AuditLog;
use App\Models\BankruptcyCase;
use App\Services\BankruptcyCaseService;
use App\Support\ActiveOrganizationContext;
use App\Support\BankruptcyRecommendationEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * إفلاس تك — كل الأفعال هنا تفترض عبور `marketplace.entitled:bankruptcy-tech`
 * أولًا (مسجَّلة على مستوى المجموعة بـroutes/web.php) — Entitlement محسوم
 * قبل الوصول هنا. Authorization لكل قضية بعينها عبر BankruptcyCasePolicy
 * (داخل Service)، لا فحص مستقل هنا.
 */
class BankruptcyCaseController extends Controller
{
    use RedirectsToCaseTab;

    public function index(): View
    {
        $user = Auth::user();
        $activeOrganization = ActiveOrganizationContext::current();

        $cases = BankruptcyCase::query()
            ->when(
                $activeOrganization,
                fn ($q) => $q->where('organization_id', $activeOrganization->id),
                fn ($q) => $q->whereNull('organization_id')->where('created_by_user_id', $user->id),
            )
            ->withCount(['parties', 'procedures'])
            ->latest()
            ->paginate(10);

        return view('bankruptcy-tech.index', [
            'cases' => $cases,
            'activeOrganization' => $activeOrganization,
        ]);
    }

    public function create(): View
    {
        return view('bankruptcy-tech.create', [
            'activeOrganization' => ActiveOrganizationContext::current(),
        ]);
    }

    public function store(StoreBankruptcyCaseRequest $request, BankruptcyCaseService $service): RedirectResponse
    {
        try {
            $case = $service->createCase(
                Auth::user(),
                ActiveOrganizationContext::current(),
                $request->string('title')->toString(),
                $request->string('description')->toString() ?: null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['title' => $e->getMessage()])->withInput();
        }

        // تُفتَح القضية الجديدة مباشرة على معالج التشخيص — توجيه المستخدم
        // فورًا للبيانات الضرورية، بدل صفحة "نظرة عامة" فارغة بلا إرشاد.
        return $this->backToCaseTab($case, 'wizard')->with('status', 'أُنشئت القضية بنجاح — أكمل معالج التشخيص أدناه.');
    }

    public function show(BankruptcyCase $case): View
    {
        Gate::authorize('view', $case);

        $case->load([
            'parties', 'procedures.creator', 'notes.user', 'documents.uploadedBy', 'creator', 'organization',
            'creditors', 'assets', 'employees', 'hearings', 'timelineEvents', 'client',
        ]);

        $timeline = AuditLog::query()
            ->where(function ($q) use ($case) {
                $q->where('subject_type', BankruptcyCase::class)->where('subject_id', $case->id);
            })
            ->orWhere(function ($q) use ($case) {
                $q->whereIn('event', [
                    'case_party_added', 'case_procedure_added', 'case_procedure_status_changed', 'case_note_added',
                    'case_document_uploaded', 'case_creditor_added', 'case_asset_added', 'case_employee_added',
                    'case_hearing_added', 'case_timeline_event_toggled', 'case_profile_updated',
                    'case_wizard_answers_updated', 'case_checklists_updated',
                ])->where('metadata->case_id', $case->id);
            })
            ->with('actor')
            ->latest()
            ->get();

        $canManage = Gate::forUser(Auth::user())->allows('manage', $case);

        $engine = app(BankruptcyRecommendationEngine::class);
        // التوصية القانونية لا تُحسَب/تُعرَض إلا بعد اكتمال معالج التشخيص —
        // عرضها ببيانات جزئية (مثلًا أصول=0 لأنها لم تُدخَل بعد) يُنتج توصية
        // بلا معنى (كل قضية جديدة فارغة كانت تظهر "تصفية إدارية" تلقائيًا).
        $isReadyForRecommendation = $engine->isReadyForRecommendation($case);
        $recommendation = $isReadyForRecommendation ? $engine->recommend($case) : null;
        $deficiencies = $engine->deficiencies($case);

        return view('bankruptcy-tech.show', compact('case', 'timeline', 'canManage', 'recommendation', 'deficiencies', 'isReadyForRecommendation'));
    }

    public function updateStatus(Request $request, BankruptcyCase $case, BankruptcyCaseService $service): RedirectResponse
    {
        try {
            $request->validate(['status' => ['required', 'string']]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->backToCaseTab($case, 'overview')->withErrors($e->errors());
        }

        try {
            $service->changeStatus(Auth::user(), $case, $request->string('status')->toString());
        } catch (InvalidArgumentException $e) {
            return $this->backToCaseTab($case, 'overview')->withErrors(['status' => $e->getMessage()]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            abort(403, $e->getMessage());
        }

        return $this->backToCaseTab($case, 'overview')->with('status', 'تم تحديث حالة القضية.');
    }

    public function destroy(BankruptcyCase $case, BankruptcyCaseService $service): RedirectResponse
    {
        $service->deleteCase(Auth::user(), $case);

        return redirect()->route('bankruptcy-tech.cases.index')->with('status', 'حُذفت القضية نهائيًا.');
    }
}
