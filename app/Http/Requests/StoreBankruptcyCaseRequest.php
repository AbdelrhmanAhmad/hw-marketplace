<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBankruptcyCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization الفعلي عبر BankruptcyCasePolicy داخل الـService — لا ازدواج هنا.
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
