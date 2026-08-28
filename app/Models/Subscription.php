<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Subscription ≠ Access — راجع marketplace-architecture-blueprint.md قسم ٤.
 * لا تُنشأ إلا عبر App\Services\SubscriptionService (AD-002 نقطة ٢).
 */
#[Fillable(['subscriber_type', 'subscriber_id', 'marketplace_item_id', 'subscription_plan_id', 'status', 'subscribed_at'])]
class Subscription extends Model
{
    protected function casts(): array
    {
        return [
            'subscribed_at' => 'datetime',
        ];
    }

    public function subscriber(): MorphTo
    {
        return $this->morphTo();
    }

    public function marketplaceItem(): BelongsTo
    {
        return $this->belongsTo(MarketplaceItem::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function accessAssignments(): HasMany
    {
        return $this->hasMany(AccessAssignment::class);
    }

    public function seats(): HasMany
    {
        return $this->hasMany(SubscriptionSeat::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
