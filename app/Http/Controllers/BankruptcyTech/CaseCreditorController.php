<?php

namespace App\Http\Controllers\BankruptcyTech;

use App\Http\Controllers\BankruptcyTech\Concerns\RedirectsToCaseTab;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCaseCreditorRequest;
use App\Models\BankruptcyCase;
use App\Services\BankruptcyCaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class CaseCreditorController extends Controller
{
    use RedirectsToCaseTab;

    public function store(StoreCaseCreditorRequest $request, BankruptcyCase $case, BankruptcyCaseService $service): RedirectResponse
    {
        try {
            $service->addCreditor(Auth::user(), $case, $request->validated());
        } catch (InvalidArgumentException $e) {
            return $this->backToCaseTab($case, 'creditors')->withErrors(['name' => $e->getMessage()])->withInput();
        }

        return $this->backToCaseTab($case, 'creditors')->with('status', 'أُضيف الدائن بنجاح.');
    }
}
