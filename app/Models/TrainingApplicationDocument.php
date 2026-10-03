<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Fillable(['training_application_id', 'type', 'original_filename', 'disk', 'path', 'mime_type', 'size_bytes'])]
class TrainingApplicationDocument extends Model
{
    public const array TYPES = [
        'cv' => 'السيرة الذاتية',
        'enrollment_letter' => 'إفادة القيد الجامعي',
        'transcript' => 'كشف الدرجات',
        'national_id' => 'صورة الهوية الوطنية',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(TrainingApplication::class, 'training_application_id');
    }

    public function label(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function humanSize(): string
    {
        $bytes = $this->size_bytes ?? 0;

        return $bytes >= 1048576
            ? number_format($bytes / 1048576, 1).' MB'
            : number_format(max($bytes, 1) / 1024, 0).' KB';
    }

    public function download(): StreamedResponse
    {
        return Storage::disk($this->disk)->download($this->path, $this->original_filename);
    }
}
