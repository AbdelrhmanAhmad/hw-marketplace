<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * يخدم نموذجين مختلفين (بيانات الملف بتبويب "نظرة عامة"، وبيانات المستند
 * بتبويب "المستندات القانونية") — حقل `_tab` المخفي يحدّد لأي تبويب نرجع
 * عند فشل التحقق (نفس منطق CaseProfileController::update، لكن هذا يعالج
 * مسار فشل FormRequest التلقائي الذي لا يمر بالـController إطلاقًا).
 */
class UpdateCaseProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'court_case_number' => ['nullable', 'string', 'max:255'],
            'debtor_name' => ['nullable', 'string', 'max:255'],
            'legal_form' => ['nullable', 'string', 'max:255'],
            'cr_number' => ['nullable', 'string', 'size:10'],
            'cr_city' => ['nullable', 'string', 'max:255'],
            'court_city' => ['nullable', 'string', 'max:255'],
            'representative_name' => ['nullable', 'string', 'max:255'],
            'representative_title' => ['nullable', 'string', 'max:255'],
            'representative_id' => ['nullable', 'string', 'max:100'],
            'attorney_name' => ['nullable', 'string', 'max:255'],
            'attorney_license' => ['nullable', 'string', 'max:100'],
            'submission_date' => ['nullable', 'date'],
            'trustee_name' => ['nullable', 'string', 'max:255'],
            // المرحلة 3 — حقول توليد المستندات القانونية.
            'document_date' => ['nullable', 'date'],
            'document_time' => ['nullable', 'string', 'max:50'],
            'poa_number' => ['nullable', 'string', 'max:100'],
            'poa_date' => ['nullable', 'date'],
            'poa_city' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function getRedirectUrl(): string
    {
        $case = $this->route('case');

        if (! $case) {
            return parent::getRedirectUrl();
        }

        $tab = in_array($this->input('_tab'), ['overview', 'legal-documents'], true) ? $this->input('_tab') : 'overview';

        return route('bankruptcy-tech.cases.show', $case).'#'.$tab;
    }
}
