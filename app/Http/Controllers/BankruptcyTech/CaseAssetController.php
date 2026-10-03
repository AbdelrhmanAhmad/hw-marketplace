<?php

namespace App\Http\Controllers\BankruptcyTech;

use App\Http\Controllers\BankruptcyTech\Concerns\RedirectsToCaseTab;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCaseAssetRequest;
use App\Models\BankruptcyCase;
use App\Services\BankruptcyCaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class CaseAssetController extends Controller
{
    use RedirectsToCaseTab;

    public function store(StoreCaseAssetRequest $request, BankruptcyCase $case, BankruptcyCaseService $service): RedirectResponse
    {
        try {
            $service->addAsset(Auth::user(), $case, $request->validated());
        } catch (InvalidArgumentException $e) {
            return $this->backToCaseTab($case, 'assets')->withErrors(['name' => $e->getMessage()])->withInput();
        }

        return $this->backToCaseTab($case, 'assets')->with('status', 'أُضيف الأصل بنجاح.');
    }
}
