<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * مجتمع الخدمات — إعلان خدمة عام (دليل محامين/مختصين للعامة، مطابق
 * لبوابة المقالات: تصفح عام بلا Auth). لا Authorization هنا — ServiceListingService
 * وحدها تقرر (نفس نمط Article/BankruptcyCase، BR-013).
 */
#[Fillable(['user_id', 'category', 'title', 'description', 'contact_method', 'contact_value', 'status', 'closed_at'])]
class ServiceListing extends Model
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

    public function inquiries(): HasMany
    {
        return $this->hasMany(ServiceListingInquiry::class);
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
