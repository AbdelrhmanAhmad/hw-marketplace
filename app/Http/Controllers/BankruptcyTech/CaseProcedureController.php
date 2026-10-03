<?php

namespace App\Http\Controllers\BankruptcyTech;

use App\Http\Controllers\BankruptcyTech\Concerns\RedirectsToCaseTab;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCaseProcedureRequest;
use App\Models\BankruptcyCase;
use App\Models\CaseProcedure;
use App\Services\BankruptcyCaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CaseProcedureController extends Controller
{
    use RedirectsToCaseTab;

    public function store(StoreCaseProcedureRequest $request, BankruptcyCase $case, BankruptcyCaseService $service): RedirectResponse
    {
        try {
            $service->addProcedure(Auth::user(), $case, $request->validated());
        } catch (InvalidArgumentException $e) {
            return $this->backToCaseTab($case, 'procedures')->withErrors(['title' => $e->getMessage()])->withInput();
        }

        return $this->backToCaseTab($case, 'procedures')->with('status', 'أُضيف الإجراء بنجاح.');
    }

    public function updateStatus(Request $request, BankruptcyCase $case, CaseProcedure $procedure, BankruptcyCaseService $service): RedirectResponse
    {
        abort_unless($procedure->bankruptcy_case_id === $case->id, 404);

        try {
            $request->validate(['status' => ['required', 'string']]);
        } catch (ValidationException $e) {
            return $this->backToCaseTab($case, 'procedures')->withErrors($e->errors());
        }

        try {
            $service->updateProcedureStatus(Auth::user(), $procedure, $request->string('status')->toString());
        } catch (InvalidArgumentException $e) {
            return $this->backToCaseTab($case, 'procedures')->withErrors(['status' => $e->getMessage()]);
        }

        return $this->backToCaseTab($case, 'procedures')->with('status', 'تم تحديث حالة الإجراء.');
    }
}
