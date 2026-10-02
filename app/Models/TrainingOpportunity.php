<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * بوابة التدريب التعاوني — فرصة تدريب ينشرها مكتب/شركة. لا Authorization
 * هنا — TrainingOpportunityService وحدها تقرر (نفس نمط ServiceListing، BR-013).
 */
#[Fillable(['user_id', 'category', 'title', 'description', 'location', 'duration', 'status', 'closed_at'])]
class TrainingOpportunity extends Model
{
    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(TrainingApplication::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
