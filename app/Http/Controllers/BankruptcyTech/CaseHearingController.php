<?php

namespace App\Http\Controllers\BankruptcyTech;

use App\Http\Controllers\BankruptcyTech\Concerns\RedirectsToCaseTab;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCaseHearingRequest;
use App\Models\BankruptcyCase;
use App\Services\BankruptcyCaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class CaseHearingController extends Controller
{
    use RedirectsToCaseTab;

    public function store(StoreCaseHearingRequest $request, BankruptcyCase $case, BankruptcyCaseService $service): RedirectResponse
    {
        try {
            $service->addHearing(Auth::user(), $case, $request->validated());
        } catch (InvalidArgumentException $e) {
            return $this->backToCaseTab($case, 'hearings')->withErrors(['date' => $e->getMessage()])->withInput();
        }

        return $this->backToCaseTab($case, 'hearings')->with('status', 'أُضيفت الجلسة بنجاح.');
    }
}
