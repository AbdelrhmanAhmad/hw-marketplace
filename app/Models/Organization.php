<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['name', 'type', 'owner_id', 'status'])]
class Organization extends Model
{
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Phase OL — راجع docs/phase-ol-implementation-specification.md.
     */
    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    /**
     * Phase 2B — راجع docs/phase-2b-organization-subscription-access-design.md.
     * لا تُنشأ إلا عبر App\Services\OrganizationSubscriptionService.
     */
    public function marketplaceSubscriptions(): MorphMany
    {
        return $this->morphMany(Subscription::class, 'subscriber');
    }
}
