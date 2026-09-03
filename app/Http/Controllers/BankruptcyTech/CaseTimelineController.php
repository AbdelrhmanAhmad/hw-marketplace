<?php

namespace App\Http\Controllers\BankruptcyTech;

use App\Http\Controllers\BankruptcyTech\Concerns\RedirectsToCaseTab;
use App\Http\Controllers\Controller;
use App\Models\BankruptcyCase;
use App\Models\CaseTimelineEvent;
use App\Services\BankruptcyCaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class CaseTimelineController extends Controller
{
    use RedirectsToCaseTab;

    public function toggle(BankruptcyCase $case, CaseTimelineEvent $event, BankruptcyCaseService $service): RedirectResponse
    {
        abort_unless($event->bankruptcy_case_id === $case->id, 404);

        $service->toggleTimelineEvent(Auth::user(), $event);

        return $this->backToCaseTab($case, 'timeline')->with('status', 'تم تحديث الجدول الزمني.');
    }
}
