<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RedirectsToCaseTab;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCaseCreditorRequest extends FormRequest
{
    use RedirectsToCaseTab;

    protected string $caseTab = 'creditors';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'priority' => ['required', Rule::in(['p1_expenses', 'p1_employees', 'p1_government', 'p2_secured', 'p3_unsecured', 'p4_deferred'])],
            'type' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
            'contact' => ['nullable', 'string', 'max:255'],
            'pledge_type' => ['nullable', Rule::in(['عقاري', 'تجاري', 'مركبة', 'معدات', 'ضمان_شخصي', 'لا_يوجد'])],
            'pledge_registered' => ['nullable', 'boolean'],
        ];
    }
}
