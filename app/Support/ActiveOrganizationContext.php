<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Phase 2A — راجع docs/phase-2a-implementation-specification.md.
 * جلسة بحتة، بلا قاعدة بيانات جديدة. لا يمنح أي صلاحية (AD-011) — يحدد
 * "بأي هوية يعمل المستخدم الآن" فقط.
 */
class ActiveOrganizationContext
{
    public static function current(): ?Organization
    {
        $organizationId = session('active_organization_id');

        if (! $organizationId || ! Auth::check()) {
            return null;
        }

        return Auth::user()->organizations()->where('organizations.id', $organizationId)->first();
    }

    public static function switchTo(User $user, Organization $organization): void
    {
        if (! $user->organizations()->where('organizations.id', $organization->id)->exists()) {
            throw new InvalidArgumentException('المستخدم ليس عضوًا بهذي المؤسسة.');
        }

        session(['active_organization_id' => $organization->id]);
    }

    public static function switchToPersonal(): void
    {
        session()->forget('active_organization_id');
    }
}
