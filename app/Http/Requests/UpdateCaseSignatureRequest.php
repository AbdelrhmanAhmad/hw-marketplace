<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCaseSignatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(['lawyer', 'representative'])],
            'data_url' => ['required', 'string', 'starts_with:data:image/png;base64,', 'max:700000'],
        ];
    }
}
