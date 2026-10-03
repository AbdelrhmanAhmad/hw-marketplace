<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** بوابة التقنية — بند بكتالوج الخدمات (يديره فريق المنصة عبر Filament حصرًا). */
#[Fillable(['title', 'slug', 'description', 'category', 'price_note', 'icon', 'is_published', 'sort_order'])]
class TechService extends Model
{
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)->orderBy('sort_order')->orderBy('title');
    }
}
