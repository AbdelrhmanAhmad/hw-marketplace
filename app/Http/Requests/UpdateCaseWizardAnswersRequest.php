<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RedirectsToCaseTab;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCaseWizardAnswersRequest extends FormRequest
{
    use RedirectsToCaseTab;

    protected string $caseTab = 'wizard';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $yesNo = ['nullable', Rule::in(['yes', 'no'])];

        return [
            'is_establishment' => ['nullable', Rule::in(['company', 'individual'])],
            'is_active' => $yesNo,
            'has_assets' => $yesNo,
            'assets_cover_expenses' => $yesNo,
            'insolvency_status' => ['nullable', Rule::in(['actual', 'upcoming'])],
            'financial_statements_available' => $yesNo,
            'financial_transactions_available' => $yesNo,
            'creditors_notified' => $yesNo,
            'operated_twelve_months' => $yesNo,
            'previous_settlement' => $yesNo,
        ];
    }
}
