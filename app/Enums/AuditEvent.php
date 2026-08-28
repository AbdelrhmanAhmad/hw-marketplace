<?php

namespace App\Enums;

/**
 * AD-001 قائمة مغلقة أصلًا بثمانية أحداث (Subscription×3, Access×2, Seat×2،
 * الأخيران بلا استخدام فعلي لحد Phase 2B). AD-009 يثبّت التمييز الدلالي:
 * أحداث Seat للمسار المؤسسي، أحداث Access وSubscription تُستخدَم بالمسارين معًا.
 * Phase OL (AD-001 مُعدَّل) أضافت حدثين: OrganizationArchived/Restored.
 * Platform Authorization — Security Hardening Pass أضافت MembershipCreated
 * وOwnershipGranted (يسدّان جزءًا من فجوة AD-016 — إنشاء العضوية وترقية
 * عضو لـOwner أصبحا مُدقَّقين؛ تغيير Role العادي/الإزالة لا يزالان بلا حدث
 * Audit، فجوة AD-016 لم تُغلَق بالكامل بعد، لم تُوسَّع هذي الدفعة عمدًا
 * خارج ما يخص مسار منح Owner تحديدًا).
 */
enum AuditEvent: string
{
    case SubscriptionCreated = 'subscription_created';
    case SubscriptionActivated = 'subscription_activated';
    case SubscriptionCancelled = 'subscription_cancelled';
    case AccessGranted = 'access_granted';
    case AccessRevoked = 'access_revoked';
    case SeatAssigned = 'seat_assigned';
    case SeatReleased = 'seat_released';
    case OrganizationArchived = 'organization_archived';
    case OrganizationRestored = 'organization_restored';
    case MembershipCreated = 'membership_created';
    case OwnershipGranted = 'ownership_granted';
}
