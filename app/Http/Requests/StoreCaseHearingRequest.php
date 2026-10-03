<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RedirectsToCaseTab;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCaseHearingRequest extends FormRequest
{
    use RedirectsToCaseTab;

    protected string $caseTab = 'hearings';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'type' => ['required', Rule::in(['جلسة_أولى', 'جلسة_موضوع', 'جلسة_قرار', 'أخرى'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'result' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
