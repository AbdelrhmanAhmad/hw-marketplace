<?php

namespace App\Http\Controllers\BankruptcyTech;

use App\Http\Controllers\BankruptcyTech\Concerns\RedirectsToCaseTab;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCasePartyRequest;
use App\Models\BankruptcyCase;
use App\Services\BankruptcyCaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class CasePartyController extends Controller
{
    use RedirectsToCaseTab;

    public function store(StoreCasePartyRequest $request, BankruptcyCase $case, BankruptcyCaseService $service): RedirectResponse
    {
        try {
            $service->addParty(Auth::user(), $case, $request->validated());
        } catch (InvalidArgumentException $e) {
            return $this->backToCaseTab($case, 'parties')->withErrors(['name' => $e->getMessage()])->withInput();
        }

        return $this->backToCaseTab($case, 'parties')->with('status', 'أُضيف الطرف بنجاح.');
    }
}
