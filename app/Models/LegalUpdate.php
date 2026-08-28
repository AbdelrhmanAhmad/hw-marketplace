<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['title', 'body', 'published_at', 'law_entry_id'])]
class LegalUpdate extends Model
{
    protected function casts(): array
    {
        return [
            'published_at' => 'date',
        ];
    }

    public function lawEntry(): BelongsTo
    {
        return $this->belongsTo(LawEntry::class);
    }
}
