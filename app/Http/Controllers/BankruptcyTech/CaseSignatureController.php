<?php

namespace App\Http\Controllers\BankruptcyTech;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCaseSignatureRequest;
use App\Models\BankruptcyCase;
use App\Services\BankruptcyCaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/** المرحلة 3 — حفظ توقيع حقيقي (Canvas)، لا علاقة بأي تحقق هوية. */
class CaseSignatureController extends Controller
{
    public function update(UpdateCaseSignatureRequest $request, BankruptcyCase $case, BankruptcyCaseService $service): JsonResponse
    {
        try {
            $service->saveSignature(Auth::user(), $case, $request->string('role')->toString(), $request->string('data_url')->toString());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'تم حفظ التوقيع.']);
    }
}
