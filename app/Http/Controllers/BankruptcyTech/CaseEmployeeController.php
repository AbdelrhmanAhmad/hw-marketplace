<?php

namespace App\Http\Controllers\BankruptcyTech;

use App\Http\Controllers\BankruptcyTech\Concerns\RedirectsToCaseTab;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCaseEmployeeRequest;
use App\Models\BankruptcyCase;
use App\Services\BankruptcyCaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class CaseEmployeeController extends Controller
{
    use RedirectsToCaseTab;

    public function store(StoreCaseEmployeeRequest $request, BankruptcyCase $case, BankruptcyCaseService $service): RedirectResponse
    {
        try {
            $service->addEmployee(Auth::user(), $case, $request->validated());
        } catch (InvalidArgumentException $e) {
            return $this->backToCaseTab($case, 'employees')->withErrors(['name' => $e->getMessage()])->withInput();
        }

        return $this->backToCaseTab($case, 'employees')->with('status', 'أُضيف الموظف بنجاح.');
    }
}
