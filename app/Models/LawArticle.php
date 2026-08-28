<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['law_entry_id', 'article_number', 'content', 'sort_order'])]
class LawArticle extends Model
{
    public function lawEntry(): BelongsTo
    {
        return $this->belongsTo(LawEntry::class);
    }
}
