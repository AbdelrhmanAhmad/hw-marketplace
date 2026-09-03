<?php

namespace App\Http\Controllers\BankruptcyTech;

use App\Http\Controllers\BankruptcyTech\Concerns\RedirectsToCaseTab;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCaseProfileRequest;
use App\Models\BankruptcyCase;
use App\Services\BankruptcyCaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class CaseProfileController extends Controller
{
    use RedirectsToCaseTab;

    /**
     * نموذجان مستقلان يستهلكان نفس هذا التابع (بيانات الملف بتبويب "نظرة
     * عامة"، وبيانات المستندات بتبويب "المستندات القانونية") — حقل مخفي
     * `_tab` يحدّد لأي تبويب نرجع (راجع RedirectsToCaseTab).
     */
    public function update(UpdateCaseProfileRequest $request, BankruptcyCase $case, BankruptcyCaseService $service): RedirectResponse
    {
        $service->updateDebtorProfile(Auth::user(), $case, $request->validated());

        $tab = in_array($request->input('_tab'), ['overview', 'legal-documents'], true) ? $request->input('_tab') : 'overview';

        return $this->backToCaseTab($case, $tab)->with('status', 'تم تحديث بيانات الملف.');
    }
}
