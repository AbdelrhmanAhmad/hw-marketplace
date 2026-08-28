<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\MarketplaceItem;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Phase 2B — نقطة الدخول الوحيدة لإنشاء/تعديل/إلغاء اشتراك مؤسسي (BR-013 نفسه،
 * مُطبَّق هنا للمسار المؤسسي). لا Subscription::create() مباشر، ولا
 * Model::update() مباشر من Filament — كل تغيير يمر من هنا.
 *
 * كل خطة (SubscriptionPlan) مخصَّصة لاشتراك واحد بمفردها (لا تُشارَك بين
 * مؤسسات) — قرار تنفيذي متعمَّد: تعديل seat_limit على خطة مُشترَكة كان
 * سيؤثر على مؤسسات أخرى تستخدم نفس الصف، وهذا خطر غير مقبول. راجع
 * docs/phase-2b-completion-report.md لتوثيق هذا القرار.
 *
 * Platform Authorization Foundation — كل تابع مُغيِّر (create/changeSeatLimit/
 * cancel) يتحقق من Authorization داخليًا عبر Gate::forUser()->authorize()،
 * ولا يثق بـFilament كحد أمني. هذا يغلق فجوة كانت موجودة منذ Phase 2B (صفر
 * تحقق داخلي)، مكتشَفة بـdocs/platform-administration-authorization-design.md.
 * راجع docs/platform-authorization-foundation-specification.md §2.
 *
 * AD-018 — create() و changeSeatLimit() (عند الزيادة) يتحققان أيضًا من
 * OrganizationMarketplaceAccessGuard — لا يكفي كون الفاعل مخوَّلًا
 * (Authorization)؛ حالة المؤسسة نفسها (Domain State) يجب تسمح بالفعل
 * أصلًا. راجع docs/organization-lifecycle-domain-state-design.md.
 *
 * إصلاح Race Condition (بعد AD-018 Security Review، Finding AD018-1) —
 * create() يقفل صف المؤسسة (`lockForUpdate`) **قبل** فحص Domain State،
 * بنفس نمط OrganizationLifecycleService::archive() تمامًا — يمنع نافذة
 * نظرية كان يمكن فيها لـcreate() يقرأ حالة "Active" قديمة بينما archive()
 * متزامنة تُغيّرها. راجع docs/ad-018-race-condition-fix-completion-report.md.
 */
class OrganizationSubscriptionService
{
    public function __construct(private readonly OrganizationMarketplaceAccessGuard $accessGuard)
    {
    }

    public function create(User $actor, Organization $organization, MarketplaceItem $item, string $planName, int $seatLimit): Subscription
    {
        Gate::forUser($actor)->authorize('manageSubscription', $organization);

        if (! in_array($item->billing_model, ['organization_only', 'both'], true)) {
            throw new InvalidArgumentException("العنصر [{$item->key}] لا يدعم اشتراكًا مؤسسيًا.");
        }

        if ($seatLimit < 1) {
            throw new InvalidArgumentException('عدد المقاعد يجب أن يكون واحدًا على الأقل.');
        }

        return DB::transaction(function () use ($actor, $organization, $item, $planName, $seatLimit) {
            // نفس قفل archive() تمامًا، ونفس الترتيب: قفل أولًا، ثم فحص
            // Domain State على الصف المقفول والطازج — لا على $organization
            // المُمرَّر (قد يكون قديمًا لو قُرئ قبل أرشفة متزامنة).
            $locked = Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            $this->accessGuard->assertCanGrantNewAccess($locked);

            $existing = $locked->marketplaceSubscriptions()
                ->where('marketplace_item_id', $item->id)
                ->first();

            if ($existing) {
                return $existing;
            }

            $plan = SubscriptionPlan::create([
                'marketplace_item_id' => $item->id,
                'name' => $planName,
                'seat_limit' => $seatLimit,
                'price' => null,
                'billing_cycle' => null,
            ]);

            /** @var Subscription $subscription */
            $subscription = new Subscription([
                'marketplace_item_id' => $item->id,
                'subscription_plan_id' => $plan->id,
                'status' => 'active',
                'subscribed_at' => now(),
            ]);
            $locked->marketplaceSubscriptions()->save($subscription);

            $this->log($actor, AuditEvent::SubscriptionCreated, $subscription, $locked);
            $this->log($actor, AuditEvent::SubscriptionActivated, $subscription, $locked);

            return $subscription;
        });
    }

    /**
     * BR-2B-08 — يمنع التخفيض تحت عدد المقاعد النشطة الحالي.
     */
    public function changeSeatLimit(User $actor, Subscription $subscription, int $newLimit): void
    {
        if ($subscription->subscriber_type !== 'organization') {
            throw new InvalidArgumentException('هذا التابع مخصَّص للاشتراكات المؤسسية فقط.');
        }

        Gate::forUser($actor)->authorize('manageSubscription', $subscription->subscriber);

        // AD-018 — الزيادة فقط تحتاج فحص حالة المؤسسة (توسّع القدرة القصوى
        // للوصول). التخفيض آمن دائمًا، لا يُقيَّد بأي حالة.
        if ($newLimit > $subscription->plan->seat_limit) {
            $this->accessGuard->assertCanGrantNewAccess($subscription->subscriber);
        }

        DB::transaction(function () use ($subscription, $newLimit) {
            $activeSeats = $subscription->seats()->active()->lockForUpdate()->count();

            if ($newLimit < $activeSeats) {
                throw new InvalidArgumentException(
                    "لا يمكن تخفيض الحد لأقل من عدد المقاعد النشطة حاليًا ({$activeSeats})."
                );
            }

            $subscription->plan->update(['seat_limit' => $newLimit]);
        });
    }

    public function cancel(User $actor, Subscription $subscription): void
    {
        if ($subscription->subscriber_type !== 'organization') {
            throw new InvalidArgumentException('هذا التابع مخصَّص للاشتراكات المؤسسية فقط.');
        }

        Gate::forUser($actor)->authorize('manageSubscription', $subscription->subscriber);

        DB::transaction(function () use ($actor, $subscription) {
            if ($subscription->status !== 'active') {
                return;
            }

            $organization = $subscription->subscriber;

            $subscription->update(['status' => 'cancelled']);
            $this->log($actor, AuditEvent::SubscriptionCancelled, $subscription, $organization);

            foreach ($subscription->seats()->active()->get() as $seat) {
                $seat->update(['status' => 'released', 'released_at' => now()]);
                $this->log($actor, AuditEvent::SeatReleased, $seat, $organization);

                $access = $subscription->accessAssignments()->where('user_id', $seat->user_id)->active()->first();
                if ($access) {
                    $access->update(['status' => 'revoked', 'revoked_at' => now()]);
                    $this->log($actor, AuditEvent::AccessRevoked, $access, $organization);
                }
            }
        });
    }

    private function log(User $actor, AuditEvent $event, $subject, Organization $organization): void
    {
        AuditLog::create([
            'organization_id' => $organization->id,
            'actor_user_id' => $actor->id,
            'event' => $event->value,
            'subject_type' => $subject::class,
            'subject_id' => $subject->id,
            'metadata' => null,
        ]);
    }
}
