<?php

namespace App\Http\Controllers\BankruptcyTech;

use App\Http\Controllers\BankruptcyTech\Concerns\RedirectsToCaseTab;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCaseDocumentRequest;
use App\Models\BankruptcyCase;
use App\Models\CaseDocument;
use App\Services\BankruptcyCaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CaseDocumentController extends Controller
{
    use RedirectsToCaseTab;

    public function store(StoreCaseDocumentRequest $request, BankruptcyCase $case, BankruptcyCaseService $service): RedirectResponse
    {
        try {
            $service->uploadDocument(Auth::user(), $case, $request->file('file'), $request->string('title')->toString());
        } catch (InvalidArgumentException $e) {
            return $this->backToCaseTab($case, 'documents')->withErrors(['file' => $e->getMessage()])->withInput();
        }

        return $this->backToCaseTab($case, 'documents')->with('status', 'أُضيف المستند بنجاح.');
    }

    /**
     * لا رابط عام مباشر للملف (القرص `local` ليس Public) — كل تنزيل يمر من
     * هنا، بعد فحص BankruptcyCasePolicy صراحة على قضية المستند (لا تسريب
     * عبر تخمين مسار).
     */
    public function download(BankruptcyCase $case, CaseDocument $document): StreamedResponse
    {
        abort_unless($document->bankruptcy_case_id === $case->id, 404);
        Gate::authorize('view', $case);

        return $document->download();
    }

    public function destroy(BankruptcyCase $case, CaseDocument $document, BankruptcyCaseService $service): RedirectResponse
    {
        abort_unless($document->bankruptcy_case_id === $case->id, 404);

        $service->deleteDocument(Auth::user(), $document);

        return $this->backToCaseTab($case, 'documents')->with('status', 'حُذف المستند.');
    }
}
