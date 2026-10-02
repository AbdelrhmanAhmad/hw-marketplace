<?php

namespace App\Http\Requests;

use App\Services\TrainingOpportunityService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTrainingOpportunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(TrainingOpportunityService::VALID_CATEGORIES)],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:100'],
            'duration' => ['nullable', 'string', 'max:100'],
        ];
    }
}
