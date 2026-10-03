<?php

namespace App\Http\Requests;

use App\Services\ServiceListingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(ServiceListingService::VALID_CATEGORIES)],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
            'contact_method' => ['required', Rule::in(ServiceListingService::VALID_CONTACT_METHODS)],
            'contact_value' => ['required', 'string', 'max:150'],
        ];
    }
}
