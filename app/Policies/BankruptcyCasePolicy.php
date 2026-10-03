<?php

namespace App\Policies;

use App\Enums\MembershipRole;
use App\Models\BankruptcyCase;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;

/**
 * إفلاس تك — Authorization مستقل تمامًا عن Entitlement:
 *
 *   Entitlement (EntitlementResolver)  = يقدر يستخدم التطبيق أصلًا؟ (AD-005/AD-013)
 *   Authorization (هذا الملف)          = يقدر يرى/يعدّل *هذي القضية بعينها*؟
 *
 * كل تابع يستعلم Membership بقاعدة البيانات مباشرة عند الاستدعاء — لا اعتماد
 * على Active Organization Context بأي شكل (AD-012، نفس نمط OrganizationPolicy
 * حرفيًا). قضية شخصية (`organization_id=null`): صاحبها فقط (+ Platform Staff).
 * قضية مؤسسية: عزل صارم بـ`organization_id` القضية نفسها — لا Membership بأي
 * مؤسسة أخرى يمنح وصولًا (Tenant Isolation).
 */
class BankruptcyCasePolicy
{
    /** عرض القضية + محتواها (أطراف/إجراءات/ملاحظات/مستندات). */
    public function view(User $user, BankruptcyCase $case): bool
    {
        return $this->hasAnyAccess($user, $case);
    }

    /** إضافة محتوى (طرف/إجراء/ملاحظة/مستند) — نفس حد الوصول العام للقضية. */
    public function contribute(User $user, BankruptcyCase $case): bool
    {
        return $this->hasAnyAccess($user, $case);
    }

    /** تعديل الحقول الجوهرية أو تغيير الحالة — أضيق (Owner/Admin بالمؤسسة، أو صاحب القضية الشخصية). */
    public function manage(User $user, BankruptcyCase $case): bool
    {
        if ($user->isPlatformStaff()) {
            return true;
        }

        if ($case->isPersonal()) {
            return $case->created_by_user_id === $user->id;
        }

        return Membership::query()
            ->where('user_id', $user->id)
            ->where('organization_id', $case->organization_id)
            ->whereIn('role', [MembershipRole::Owner, MembershipRole::Admin])
            ->exists();
    }

    /** إنشاء قضية جديدة بسياق مؤسسة معيَّنة (شخصيًا مسموح دائمًا لو وصل هنا أصلًا — Entitlement يحسم ذلك قبل الوصول). */
    public function createForOrganization(User $user, ?Organization $organization): bool
    {
        if (! $organization) {
            return true;
        }

        if ($user->isPlatformStaff()) {
            return true;
        }

        return Membership::query()
            ->where('user_id', $user->id)
            ->where('organization_id', $organization->id)
            ->exists();
    }

    /**
     * المرحلة 2 — هوية "عميل القضية" (المدين)، مستقلة تمامًا عن hasAnyAccess()
     * أعلاه (لا Membership، لا Platform Staff bypass عمدًا — مسار ضيق مقصود).
     */
    public function viewAsClient(User $user, BankruptcyCase $case): bool
    {
        return $case->client_user_id === $user->id && $case->client_access_revoked_at === null;
    }

    /** نفس حد viewAsClient اليوم — رفع مستند هو الفعل الوحيد المتاح للعميل. */
    public function contributeAsClient(User $user, BankruptcyCase $case): bool
    {
        return $this->viewAsClient($user, $case);
    }

    private function hasAnyAccess(User $user, BankruptcyCase $case): bool
    {
        if ($user->isPlatformStaff()) {
            return true;
        }

        if ($case->isPersonal()) {
            return $case->created_by_user_id === $user->id;
        }

        return Membership::query()
            ->where('user_id', $user->id)
            ->where('organization_id', $case->organization_id)
            ->exists();
    }
}
