<?php

namespace App\Http\Controllers\BankruptcyTech;

use App\Http\Controllers\BankruptcyTech\Concerns\RedirectsToCaseTab;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCaseNoteRequest;
use App\Models\BankruptcyCase;
use App\Services\BankruptcyCaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class CaseNoteController extends Controller
{
    use RedirectsToCaseTab;

    public function store(StoreCaseNoteRequest $request, BankruptcyCase $case, BankruptcyCaseService $service): RedirectResponse
    {
        try {
            $service->addNote(Auth::user(), $case, $request->string('body')->toString());
        } catch (InvalidArgumentException $e) {
            return $this->backToCaseTab($case, 'notes')->withErrors(['body' => $e->getMessage()])->withInput();
        }

        return $this->backToCaseTab($case, 'notes')->with('status', 'أُضيفت الملاحظة.');
    }
}
