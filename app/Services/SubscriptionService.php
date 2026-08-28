<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Models\AccessAssignment;
use App\Models\AuditLog;
use App\Models\MarketplaceItem;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * نقطة الدخول الوحيدة لإنشاء/تفعيل/إلغاء Subscription — لا Subscription::create()
 * مباشر من أي Controller/Livewire/Filament بأي مكان آخر (AD-002 نقطة ٢، BR-013).
 *
 * نطاق Phase 1b: اشتراك شخصي (subscriber_type=user) بتطبيقات مجانية فقط.
 * لا Billing، لا Organization، لا Seats.
 */
class SubscriptionService
{
    /**
     * $metadata — L2 Safe Migration Specification قسم 8: يُمرَّر فقط من
     * App\Services\LegacyMigrationService (Migration Provenance)، null دائمًا
     * لأي استدعاء مستخدم مباشر (السلوك الافتراضي، بلا تغيير).
     */
    public function subscribeUserToFreeItem(User $user, MarketplaceItem $item, ?array $metadata = null): Subscription
    {
        if ($item->pricing_model !== 'free') {
            throw new InvalidArgumentException("العنصر [{$item->key}] ليس مجانيًا — لا اشتراك ذاتي مسموح به هنا.");
        }

        if ($item->billing_model === 'organization_only') {
            throw new InvalidArgumentException("العنصر [{$item->key}] يتطلب اشتراك مؤسسة — غير مدعوم بـPhase 1b.");
        }

        return DB::transaction(function () use ($user, $item, $metadata) {
            $plan = SubscriptionPlan::firstOrCreate(
                ['marketplace_item_id' => $item->id, 'name' => 'Free'],
                ['seat_limit' => null, 'price' => null, 'billing_cycle' => null],
            );

            /** @var Subscription|null $subscription */
            $subscription = $user->marketplaceSubscriptions()
                ->where('marketplace_item_id', $item->id)
                ->first();

            $isNew = $subscription === null;
            $wasInactive = $subscription && $subscription->status !== 'active';

            if ($isNew) {
                $subscription = new Subscription([
                    'marketplace_item_id' => $item->id,
                    'subscription_plan_id' => $plan->id,
                    'status' => 'active',
                    'subscribed_at' => now(),
                ]);
                $user->marketplaceSubscriptions()->save($subscription);

                $this->log($user, AuditEvent::SubscriptionCreated, $subscription, $metadata);
                $this->log($user, AuditEvent::SubscriptionActivated, $subscription, $metadata);
            } elseif ($wasInactive) {
                $subscription->update(['status' => 'active', 'subscribed_at' => now()]);

                $this->log($user, AuditEvent::SubscriptionActivated, $subscription, $metadata);
            } else {
                // اشتراك فعّال أصلًا — لا فعل، لا تسجيل تدقيق إضافي (لا ازدواجية، Duplicate scenario).
                return $subscription;
            }

            $access = AccessAssignment::updateOrCreate(
                ['user_id' => $user->id, 'subscription_id' => $subscription->id],
                ['status' => 'active', 'granted_at' => now(), 'revoked_at' => null],
            );

            $this->log($user, AuditEvent::AccessGranted, $access, $metadata);

            return $subscription;
        });
    }

    public function cancel(Subscription $subscription, ?array $metadata = null): void
    {
        DB::transaction(function () use ($subscription, $metadata) {
            if ($subscription->status !== 'active') {
                return;
            }

            $subscription->update(['status' => 'cancelled']);

            /** @var User|null $actor */
            $actor = $subscription->subscriber;

            $this->log($actor, AuditEvent::SubscriptionCancelled, $subscription, $metadata);

            $access = $subscription->accessAssignments()->active()->first();

            if ($access) {
                $access->update(['status' => 'revoked', 'revoked_at' => now()]);
                $this->log($actor, AuditEvent::AccessRevoked, $access, $metadata);
            }
        });
    }

    private function log(User $actor, AuditEvent $event, $subject, ?array $metadata = null): void
    {
        AuditLog::create([
            'organization_id' => null,
            'actor_user_id' => $actor->id,
            'event' => $event->value,
            'subject_type' => $subject::class,
            'subject_id' => $subject->id,
            'metadata' => $metadata,
        ]);
    }
}
