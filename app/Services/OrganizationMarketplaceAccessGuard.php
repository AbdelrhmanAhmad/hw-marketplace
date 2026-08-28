<?php

namespace App\Services;

use App\Models\Organization;
use InvalidArgumentException;

/**
 * AD-018 — نقطة التحقق المركزية الوحيدة لسؤال "هل حالة هذي المؤسسة تسمح
 * بمنح Marketplace Access جديد/موسَّع؟" — منفصل تمامًا عن Authorization:
 *
 *   Authorization = من يستطيع تنفيذ الفعل؟           → Gate/Policy (يأخذ User)
 *   Domain State  = هل حالة الكيان تسمح بالفعل أصلًا؟  → هذا الصف (لا يأخذ User)
 *
 * عمدًا **ليست** Laravel Policy تقليدية (لا `Gate::authorize()`، لا معامل
 * `User`) — التسمية "Guard" لا "Policy" لتفادي الخلط مع `OrganizationPolicy`
 * (Gate-based، تأخذ User دائمًا). الفحص لا يعتمد على هوية الفاعل إطلاقًا:
 * Platform Staff أو Owner حقيقي كلاهما يُرفَضان بنفس القوة لو المؤسسة
 * مؤرشَفة — راجع docs/organization-lifecycle-domain-state-design.md.
 *
 * نقاط الاستدعاء الوحيدة المصرَّح بها: OrganizationSubscriptionService::create()،
 * OrganizationSubscriptionService::changeSeatLimit() (عند الزيادة فقط)،
 * SeatService::assign(). **لا نسخ لمنطق `if archived` بأي مكان آخر** — أي
 * عملية جديدة تمنح Marketplace Access مستقبلًا يجب تستدعي هذي الطبقة حصرًا.
 *
 * لا تُستدعى من Membership operations (add/changeRole/remove) — Membership
 * ليست Marketplace Access بذاتها (قرار صريح، AD-007/AD-018)؛ ولا من
 * transferOwnership() (محكومة بقواعد Ownership المستقلة، AD-017).
 */
class OrganizationMarketplaceAccessGuard
{
    public function assertCanGrantNewAccess(Organization $organization): void
    {
        if ($organization->isArchived()) {
            throw new InvalidArgumentException('لا يمكن منح وصول Marketplace جديد أو موسَّع لمؤسسة مؤرشَفة — استعد المؤسسة أولًا.');
        }
    }
}
