<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RedirectsToCaseTab;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCaseClientRequest extends FormRequest
{
    use RedirectsToCaseTab;

    protected string $caseTab = 'client';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
        ];
    }
}
