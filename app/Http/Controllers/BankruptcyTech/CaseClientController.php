<?php

namespace App\Http\Controllers\BankruptcyTech;

use App\Http\Controllers\BankruptcyTech\Concerns\RedirectsToCaseTab;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCaseClientRequest;
use App\Models\BankruptcyCase;
use App\Services\BankruptcyCaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/** جانب المحامي — دعوة/إلغاء/استعادة وصول عميل القضية (المرحلة 2). */
class CaseClientController extends Controller
{
    use RedirectsToCaseTab;

    public function store(StoreCaseClientRequest $request, BankruptcyCase $case, BankruptcyCaseService $service): RedirectResponse
    {
        try {
            $service->inviteClient(Auth::user(), $case, $request->string('name')->toString(), $request->string('email')->toString());
        } catch (InvalidArgumentException $e) {
            return $this->backToCaseTab($case, 'client')->withErrors(['email' => $e->getMessage()])->withInput();
        }

        return $this->backToCaseTab($case, 'client')->with('status', 'أُرسلت دعوة للعميل عبر البريد الإلكتروني.');
    }

    public function revoke(BankruptcyCase $case, BankruptcyCaseService $service): RedirectResponse
    {
        try {
            $service->revokeClientAccess(Auth::user(), $case);
        } catch (InvalidArgumentException $e) {
            return $this->backToCaseTab($case, 'client')->withErrors(['client' => $e->getMessage()]);
        }

        return $this->backToCaseTab($case, 'client')->with('status', 'أُلغي وصول العميل.');
    }

    public function restore(BankruptcyCase $case, BankruptcyCaseService $service): RedirectResponse
    {
        try {
            $service->restoreClientAccess(Auth::user(), $case);
        } catch (InvalidArgumentException $e) {
            return $this->backToCaseTab($case, 'client')->withErrors(['client' => $e->getMessage()]);
        }

        return $this->backToCaseTab($case, 'client')->with('status', 'استُعيد وصول العميل.');
    }
}
