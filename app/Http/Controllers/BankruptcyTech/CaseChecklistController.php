<?php

namespace App\Http\Controllers\BankruptcyTech;

use App\Http\Controllers\BankruptcyTech\Concerns\RedirectsToCaseTab;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCaseChecklistsRequest;
use App\Models\BankruptcyCase;
use App\Services\BankruptcyCaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class CaseChecklistController extends Controller
{
    use RedirectsToCaseTab;

    public function update(UpdateCaseChecklistsRequest $request, BankruptcyCase $case, BankruptcyCaseService $service): RedirectResponse
    {
        $service->updateChecklists(Auth::user(), $case, $request->validated());

        return $this->backToCaseTab($case, 'checklists')->with('status', 'تم تحديث القوائم التنظيمية.');
    }
}
