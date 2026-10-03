<?php

namespace App\Http\Controllers\BankruptcyTech;

use App\Http\Controllers\BankruptcyTech\Concerns\RedirectsToCaseTab;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCaseWizardAnswersRequest;
use App\Models\BankruptcyCase;
use App\Services\BankruptcyCaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class CaseWizardController extends Controller
{
    use RedirectsToCaseTab;

    public function update(UpdateCaseWizardAnswersRequest $request, BankruptcyCase $case, BankruptcyCaseService $service): RedirectResponse
    {
        try {
            $service->updateWizardAnswers(Auth::user(), $case, $request->validated());
        } catch (InvalidArgumentException $e) {
            return $this->backToCaseTab($case, 'wizard')->withErrors(['wizard' => $e->getMessage()])->withInput();
        }

        return $this->backToCaseTab($case, 'wizard')->with('status', 'تم تحديث معالج التشخيص.');
    }
}
