<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Platform Authorization Foundation — Platform Staff هو محور صلاحية مستقل
 * تمامًا عن Organization Role (Owner/Member). لا Synchronization بينهما،
 * لا Staff يصبح Owner/Member تلقائيًا، ولا العكس. راجع
 * docs/platform-authorization-foundation-specification.md.
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function bookmarkedLaws(): BelongsToMany
    {
        return $this->belongsToMany(LawEntry::class, 'bookmarks');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'memberships');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(AppSubscription::class);
    }

    public function hasActiveSubscription(string $appKey): bool
    {
        return $this->subscriptions()->active()->where('app_key', $appKey)->exists();
    }

    public function isPlatformStaff(): bool
    {
        return (bool) $this->is_platform_staff;
    }

    /**
     * إغلاق /admin الفعلي — هذا التابع يُستدعى بواسطة Filament قبل عرض أي
     * صفحة، لا اعتماد على إخفاء واجهة. راجع Goal 1 بالوثيقة أعلاه.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isPlatformStaff();
    }

    /**
     * النظام الجديد (Phase 1b) — بمعزل تام عن subscriptions()/hasActiveSubscription()
     * أعلاه (النظام القديم، Core Platform Phase 1، غير مُمَس). راجع
     * docs/phase-1b-completion-report.md لتوثيق التعايش المؤقت بين النظامين.
     */
    public function marketplaceSubscriptions(): MorphMany
    {
        return $this->morphMany(Subscription::class, 'subscriber');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_staff' => 'boolean',
        ];
    }
}
