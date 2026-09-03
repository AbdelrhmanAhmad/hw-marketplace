<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['bankruptcy_case_id', 'title', 'original_filename', 'disk', 'path', 'mime_type', 'size_bytes', 'uploaded_by_user_id'])]
class CaseDocument extends Model
{
    protected $table = 'bankruptcy_case_documents';

    public function bankruptcyCase(): BelongsTo
    {
        return $this->belongsTo(BankruptcyCase::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function humanSize(): string
    {
        $bytes = $this->size_bytes ?? 0;

        return $bytes >= 1048576
            ? number_format($bytes / 1048576, 1).' MB'
            : number_format(max($bytes, 1) / 1024, 0).' KB';
    }

    public function download(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return Storage::disk($this->disk)->download($this->path, $this->original_filename);
    }
}
