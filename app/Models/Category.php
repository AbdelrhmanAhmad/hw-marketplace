<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug'])]
class Category extends Model
{
    public function lawEntries(): BelongsToMany
    {
        return $this->belongsToMany(LawEntry::class, 'category_law_entry');
    }
}
