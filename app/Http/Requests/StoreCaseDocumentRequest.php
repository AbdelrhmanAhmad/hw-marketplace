<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * مشترك بين جانب المحامي (CaseDocumentController، تبويب "المستندات") وبوابة
 * العميل الخارجية (ClientPortalController، المرحلة 2) — لهذا لا يستخدم
 * الـTrait العام مباشرة (اللي يفترض دائمًا مسار المحامي)؛ getRedirectUrl()
 * هنا يتحقق من اسم المسار الفعلي أولًا لتفادي تحويل العميل لصفحة لا يملك
 * صلاحية دخولها (بوابة العميل لا تملك تبويبات أصلًا — بلا #fragment).
 */
class StoreCaseDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ];
    }

    protected function getRedirectUrl(): string
    {
        $case = $this->route('case');

        if (! $case) {
            return parent::getRedirectUrl();
        }

        if (str_starts_with((string) $this->route()?->getName(), 'client-portal.')) {
            return route('client-portal.cases.show', $case);
        }

        return route('bankruptcy-tech.cases.show', $case).'#documents';
    }
}
