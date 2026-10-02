<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** بوابة التدريب التعاوني — مستندات التقديم: pdf أو صورة، حتى 5 ميجا لكل ملف. */
class StoreTrainingApplicationRequest extends FormRequest
{
    private const array FILE_RULES = ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string', 'max:1000'],
            'cv' => ['required', ...self::FILE_RULES],
            'enrollment_letter' => ['required', ...self::FILE_RULES],
            'transcript' => ['nullable', ...self::FILE_RULES],
            'national_id' => ['nullable', ...self::FILE_RULES],
        ];
    }

    public function attributes(): array
    {
        return [
            'cv' => 'السيرة الذاتية',
            'enrollment_letter' => 'إفادة القيد الجامعي',
            'transcript' => 'كشف الدرجات',
            'national_id' => 'صورة الهوية الوطنية',
        ];
    }
}
