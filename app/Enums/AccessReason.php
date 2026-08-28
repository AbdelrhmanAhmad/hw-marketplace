<?php

namespace App\Enums;

/**
 * ناتج EntitlementResolver — Entitlement فقط، لا Authorization (AD-005).
 * فقط الحالات القابلة للحدوث فعليًا بـPhase 1b (شخصي، تطبيقات مجانية).
 * حالات المؤسسة (needs_org_membership وغيرها) تُضاف لاحقًا بـPhase 2، لا الآن.
 */
enum AccessReason: string
{
    case HasAccess = 'has_access';
    case NeedsAccess = 'needs_access';
    case NeedsSubscription = 'needs_subscription';
    case ItemUnavailable = 'item_unavailable';

    /**
     * Phase 2B — العنصر يتطلب اشتراكًا مؤسسيًا (billing_model=organization_only)
     * ولا اشتراك/مقعد فعّال بالمؤسسة النشطة حاليًا. راجع phase-2-organization-access-design.md قسم H.
     */
    case NeedsOrgMembership = 'needs_org_membership';
}
