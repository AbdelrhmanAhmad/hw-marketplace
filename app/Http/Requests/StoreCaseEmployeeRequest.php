<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RedirectsToCaseTab;
use Illuminate\Foundation\Http\FormRequest;

class StoreCaseEmployeeRequest extends FormRequest
{
    use RedirectsToCaseTab;

    protected string $caseTab = 'employees';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'nationality' => ['nullable', 'string', 'max:255'],
            'iqama' => ['nullable', 'string', 'max:100'],
            'salary' => ['required', 'numeric', 'min:0.01'],
            'join_date' => ['required', 'date'],
        ];
    }
}
