<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title',
    'slug',
    'number',
    'hijri_date',
    'gregorian_date',
    'status',
    'issuing_authority',
    'summary',
    'source_url',
    'external_id',
])]
class LawEntry extends Model
{
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function casts(): array
    {
        return [
            'gregorian_date' => 'date',
        ];
    }

    public function articles(): HasMany
    {
        return $this->hasMany(LawArticle::class)->orderBy('sort_order');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_law_entry');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(LegalUpdate::class);
    }

    public function bookmarkedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'bookmarks');
    }
}
