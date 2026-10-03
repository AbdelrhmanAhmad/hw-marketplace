<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RedirectsToCaseTab;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCaseChecklistsRequest extends FormRequest
{
    use RedirectsToCaseTab;

    protected string $caseTab = 'checklists';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'zatca_file_number' => ['nullable', 'string', 'max:255'],
            'zatca_checklist' => ['nullable', 'array'],
            'zatca_checklist.accountStatement' => ['nullable', 'boolean'],
            'zatca_checklist.vatRegistration' => ['nullable', 'boolean'],
            'zatca_checklist.zakahCert' => ['nullable', 'boolean'],
            'zatca_checklist.clearanceLetter' => ['nullable', 'boolean'],
            'gosi_file_number' => ['nullable', 'string', 'max:255'],
            'gosi_checklist' => ['nullable', 'array'],
            'gosi_checklist.registered' => ['nullable', 'boolean'],
            'gosi_checklist.debtsStatement' => ['nullable', 'boolean'],
            'gosi_checklist.clearanceLetter' => ['nullable', 'boolean'],
            'hr_checklist' => ['nullable', 'array'],
            'hr_checklist.employeesListed' => ['nullable', 'boolean'],
            'hr_checklist.mudadCleared' => ['nullable', 'boolean'],
            'hr_checklist.workPermitsCancelled' => ['nullable', 'boolean'],
            'commerce_cr_cancellation_requested' => ['nullable', 'boolean'],
            'sama_notified' => ['nullable', 'boolean'],
        ];
    }
}
