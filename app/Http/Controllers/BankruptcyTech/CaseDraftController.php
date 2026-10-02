<?php

namespace App\Http\Controllers\BankruptcyTech;

use App\Http\Controllers\BankruptcyTech\Concerns\RedirectsToCaseTab;
use App\Http\Controllers\Controller;
use App\Models\BankruptcyCase;
use App\Services\CaseDraftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class CaseDraftController extends Controller
{
    use RedirectsToCaseTab;

    public function store(BankruptcyCase $case, CaseDraftService $service): RedirectResponse
    {
        try {
            $service->generateDraft(Auth::user(), $case);
        } catch (InvalidArgumentException $e) {
            return $this->backToCaseTab($case, 'ai-draft')->withErrors(['ai_draft' => $e->getMessage()]);
        }

        return $this->backToCaseTab($case, 'ai-draft')->with('status', 'أُنشئت مسودة جديدة.');
    }
}
