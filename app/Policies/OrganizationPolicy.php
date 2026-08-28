<?php

namespace App\Policies;

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;

/**
 * AD-012 — كل تابع هنا يستعلم Membership بقاعدة البيانات مباشرة عند
 * الاستدعاء، لا يعتمد على Active Organization Context بأي شكل.
 * أول Policy حقيقية بالمشروع كاملًا (لا Policies كانت موجودة قبل Phase 2B).
 *
 * Platform Authorization Foundation — كل تابع يضيف `$user->isPlatformStaff()`
 * صراحة كشرط OR مستقل (لا Gate::before() شامل، قرار مصمَّم بالتفصيل في
 * docs/platform-authorization-foundation-specification.md §5). Platform
 * Staff محور صلاحية منفصل تمامًا عن Organization Role — لا يُسجَّل بجدول
 * Membership، ولا يُحتسَب ضمن أي عدّ Owner (Last Owner Rule لا تتأثر).
 */
class OrganizationPolicy
{
    /**
     * BR-2B-01 — إنشاء/تعديل خطة الاشتراك المؤسسي: Owner أو Platform Staff.
     */
    public function manageSubscription(User $user, Organization $organization): bool
    {
        return $user->isPlatformStaff() || Membership::query()
            ->where('user_id', $user->id)
            ->where('organization_id', $organization->id)
            ->where('role', MembershipRole::Owner)
            ->exists();
    }

    /**
     * BR-2B-02 — تعيين/سحب المقاعد: Owner أو Admin أو Platform Staff.
     */
    public function manageSeats(User $user, Organization $organization): bool
    {
        return $user->isPlatformStaff() || Membership::query()
            ->where('user_id', $user->id)
            ->where('organization_id', $organization->id)
            ->whereIn('role', [MembershipRole::Owner, MembershipRole::Admin])
            ->exists();
    }

    /**
     * Phase OI — تعديل/حذف عضوية: Owner أو Admin أو Platform Staff.
     */
    public function manageMembers(User $user, Organization $organization): bool
    {
        return $user->isPlatformStaff() || Membership::query()
            ->where('user_id', $user->id)
            ->where('organization_id', $organization->id)
            ->whereIn('role', [MembershipRole::Owner, MembershipRole::Admin])
            ->exists();
    }

    /**
     * Phase OI — نقل الملكية: Owner أو Platform Staff.
     */
    public function transferOwnership(User $user, Organization $organization): bool
    {
        return $user->isPlatformStaff() || Membership::query()
            ->where('user_id', $user->id)
            ->where('organization_id', $organization->id)
            ->where('role', MembershipRole::Owner)
            ->exists();
    }

    /**
     * Phase OL — أرشفة مؤسسة: Owner أو Platform Staff.
     */
    public function archive(User $user, Organization $organization): bool
    {
        return $user->isPlatformStaff() || Membership::query()
            ->where('user_id', $user->id)
            ->where('organization_id', $organization->id)
            ->where('role', MembershipRole::Owner)
            ->exists();
    }

    /**
     * Phase OL — استعادة مؤسسة مؤرشَفة: Owner أو Platform Staff (نفس منطق archive).
     */
    public function restore(User $user, Organization $organization): bool
    {
        return $user->isPlatformStaff() || Membership::query()
            ->where('user_id', $user->id)
            ->where('organization_id', $organization->id)
            ->where('role', MembershipRole::Owner)
            ->exists();
    }
}
