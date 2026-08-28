<?php

namespace App\Models;

use App\Enums\MembershipRole;
use App\Events\MembershipRevoked;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'organization_id', 'role'])]
class Membership extends Model
{
    protected function casts(): array
    {
        return [
            'role' => MembershipRole::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Phase 2B (BR-2B-04) — إضافة بحتة، لا تغيير على أي سلوك موجود.
     */
    protected static function booted(): void
    {
        static::deleted(function (self $membership) {
            event(new MembershipRevoked($membership->user_id, $membership->organization_id));
        });
    }
}
