<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AD-008 — Seat = تخصيص إداري داخل الاشتراك، منفصل عن AccessAssignment
 * (صلاحية الاستخدام الفعلية). لا تُنشأ/تُعدَّل إلا عبر App\Services\SeatService.
 */
#[Fillable(['subscription_id', 'user_id', 'status', 'assigned_at', 'released_at'])]
class SubscriptionSeat extends Model
{
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'assigned');
    }
}
