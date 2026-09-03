<?php

namespace App\Http\Controllers\BankruptcyTech;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCaseDocumentRequest;
use App\Models\BankruptcyCase;
use App\Models\CaseDocument;
use App\Services\BankruptcyCaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * بوابة العميل الخارجية (المرحلة 2) — المدين نفسه، بلا Membership/Organization
 * وبلا اشتراك Marketplace. مسار مستقل تمامًا عن `marketplace.entitled:
 * bankruptcy-tech` (راجع routes/web.php) — Authorization فقط عبر
 * BankruptcyCasePolicy::viewAsClient/contributeAsClient، لا فحص Entitlement هنا.
 */
class ClientPortalController extends Controller
{
    public function show(BankruptcyCase $case): View
    {
        Gate::authorize('viewAsClient', $case);

        $case->load('documents.uploadedBy');

        return view('bankruptcy-tech.client-portal', compact('case'));
    }

    public function storeDocument(StoreCaseDocumentRequest $request, BankruptcyCase $case, BankruptcyCaseService $service): RedirectResponse
    {
        try {
            $service->uploadDocumentAsClient(Auth::user(), $case, $request->file('file'), $request->string('title')->toString());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['file' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'أُضيف المستند بنجاح.');
    }

    public function downloadDocument(BankruptcyCase $case, CaseDocument $document): StreamedResponse
    {
        abort_unless($document->bankruptcy_case_id === $case->id, 404);
        Gate::authorize('viewAsClient', $case);

        return $document->download();
    }
}
