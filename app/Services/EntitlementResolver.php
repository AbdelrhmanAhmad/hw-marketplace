<?php

namespace App\Services;

use App\Enums\AccessReason;
use App\Models\MarketplaceItem;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\User;

/**
 * القرار المركزي "هل هذا المستخدم مؤهَّل يستخدم هذا العنصر؟" — Entitlement
 * فقط، لا Authorization (AD-005). لا يُستخدَم أبدًا لتحديد صلاحيات داخل
 * تطبيق (BR-016) — كل تطبيق يبني طبقة Authorization خاصة به بمعزل تام.
 *
 * الخطوات ١-٤: شخصي (Phase 1b، بلا تغيير). الخطوة ٥: مؤسسي عبر Seat
 * (Phase 2B) — تُفعَّل فقط لو مُرِّر $activeOrganization صراحة، ولا تُستهلَك
 * إلا بعد إعادة تحقق العضوية بالخدمات المستهلِكة (AD-012).
 */
class EntitlementResolver
{
    public function resolve(User $user, MarketplaceItem $item, ?Organization $activeOrganization = null): AccessDecision
    {
        if (! in_array($item->status, ['published', 'updated'], true)) {
            return new AccessDecision(false, AccessReason::ItemUnavailable);
        }

        $personalSubscription = $user->marketplaceSubscriptions()
            ->where('marketplace_item_id', $item->id)
            ->first();

        if ($personalSubscription) {
            $hasActiveAccess = $personalSubscription->accessAssignments()
                ->where('user_id', $user->id)
                ->active()
                ->exists();

            if ($personalSubscription->status === 'active' && $hasActiveAccess) {
                return new AccessDecision(true, AccessReason::HasAccess);
            }

            if ($personalSubscription->status === 'active' && ! $hasActiveAccess) {
                return new AccessDecision(false, AccessReason::NeedsAccess);
            }
        }

        if ($activeOrganization && in_array($item->billing_model, ['organization_only', 'both'], true)) {
            $orgSubscription = Subscription::where('subscriber_type', 'organization')
                ->where('subscriber_id', $activeOrganization->id)
                ->where('marketplace_item_id', $item->id)
                ->where('status', 'active')
                ->first();

            if ($orgSubscription) {
                $hasSeatAccess = $orgSubscription->accessAssignments()
                    ->where('user_id', $user->id)
                    ->active()
                    ->exists();

                if ($hasSeatAccess) {
                    return new AccessDecision(true, AccessReason::HasAccess);
                }

                return new AccessDecision(false, AccessReason::NeedsOrgMembership);
            }
        }

        return new AccessDecision(false, AccessReason::NeedsSubscription);
    }
}
