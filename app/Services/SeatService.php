<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Subscription;
use App\Models\SubscriptionSeat;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Phase 2B — نقطة الدخول الوحيدة لتعيين/سحب/إعادة تعيين المقاعد.
 * AD-003/BR-2B-07: Transaction + lockForUpdate + تحقق خادم صريح، خط دفاع DB
 * إضافي (UNIQUE على subscription_seats). راجع
 * docs/phase-2b-organization-subscription-access-design.md قسم B (حالة ٧).
 *
 * Security Hardening Pass (Finding #2) — assign()/release() تتحقق من
 * Authorization داخليًا الآن (Gate::forUser($actor)->authorize('manageSeats', ...))،
 * لا تثق بالمُستدعي وحده (كانت الفجوة: OrganizationSeatController كان خط
 * الدفاع الوحيد، بنفس فئة الثغرة المُصلَحة سابقًا بـOrganizationSubscriptionService).
 * releaseAllForUserInOrganization() تستدعي performRelease() الداخلية مباشرة
 * (بلا Gate) عمدًا — هي نتيجة نظامية لحدث MembershipRevoked الذي يُطلَق فقط
 * بعد MembershipService::remove() تحقق من Authorization أصلًا؛ إعادة الفحص
 * هنا يكسر حالة Fallback الموثَّقة بـReleaseSeatsOnMembershipRevoked (العضو
 * المُزال نفسه كـactor بالسياقات غير-HTTP) بلا أي فائدة أمنية حقيقية. راجع
 * docs/platform-authorization-hardening-completion-report.md.
 *
 * AD-018 — assign() يتحقق أيضًا من OrganizationMarketplaceAccessGuard —
 * تعيين مقعد يمنح وصولًا فعليًا، فيُرفَض لمؤسسة مؤرشَفة بصرف النظر عن
 * تخويل الفاعل. راجع docs/organization-lifecycle-domain-state-design.md.
 */
class SeatService
{
    public function __construct(private readonly OrganizationMarketplaceAccessGuard $accessGuard)
    {
    }

    public function assign(User $actor, Subscription $subscription, User $targetUser): SubscriptionSeat
    {
        if ($subscription->subscriber_type !== 'organization') {
            throw new InvalidArgumentException('هذا التابع مخصَّص للاشتراكات المؤسسية فقط.');
        }

        $organization = $subscription->subscriber;

        Gate::forUser($actor)->authorize('manageSeats', $organization);
        $this->accessGuard->assertCanGrantNewAccess($organization);

        // AD-012 — تحقق مباشر من العضوية الفعلية، لا اعتماد على أي سياق مررَّر.
        $isMember = Membership::query()
            ->where('user_id', $targetUser->id)
            ->where('organization_id', $organization->id)
            ->exists();

        if (! $isMember) {
            throw new InvalidArgumentException('لا يمكن تعيين مقعد لمستخدم ليس عضوًا بهذي المؤسسة.');
        }

        return DB::transaction(function () use ($actor, $subscription, $targetUser, $organization) {
            // BR-2B-07 — قفل صف الاشتراك طوال المعاملة، إعادة العدّ داخل القفل.
            $lockedSubscription = Subscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();

            $existingSeat = $lockedSubscription->seats()
                ->where('user_id', $targetUser->id)
                ->first();

            if ($existingSeat && $existingSeat->status === 'assigned') {
                return $existingSeat;
            }

            $activeCount = $lockedSubscription->seats()->active()->count();
            $seatLimit = $lockedSubscription->plan->seat_limit;

            if ($seatLimit !== null && $activeCount >= $seatLimit) {
                throw new InvalidArgumentException('لا مقاعد متاحة — تم الوصول للحد الأقصى.');
            }

            $seat = SubscriptionSeat::updateOrCreate(
                ['subscription_id' => $lockedSubscription->id, 'user_id' => $targetUser->id],
                ['status' => 'assigned', 'assigned_at' => now(), 'released_at' => null],
            );

            $this->log($actor, AuditEvent::SeatAssigned, $seat, $organization);

            $access = $lockedSubscription->accessAssignments()->where('user_id', $targetUser->id)->first();
            if ($access) {
                $access->update(['status' => 'active', 'granted_at' => now(), 'revoked_at' => null]);
            } else {
                $access = $lockedSubscription->accessAssignments()->create([
                    'user_id' => $targetUser->id,
                    'status' => 'active',
                    'granted_at' => now(),
                ]);
            }

            $this->log($actor, AuditEvent::AccessGranted, $access, $organization);

            return $seat;
        });
    }

    public function release(User $actor, SubscriptionSeat $seat): void
    {
        if ($seat->status !== 'assigned') {
            return;
        }

        Gate::forUser($actor)->authorize('manageSeats', $seat->subscription->subscriber);

        $this->performRelease($actor, $seat);
    }

    public function reassign(User $actor, SubscriptionSeat $seat, User $newUser): SubscriptionSeat
    {
        $subscription = $seat->subscription;

        return DB::transaction(function () use ($actor, $seat, $newUser, $subscription) {
            $this->release($actor, $seat);

            return $this->assign($actor, $subscription->fresh(), $newUser);
        });
    }

    /**
     * BR-2B-04 — مستهلك حدث MembershipRevoked (راجع App\Listeners\ReleaseSeatsOnMembershipRevoked).
     * لا يمسّ الاشتراك المؤسسي نفسه، فقط مقاعد ذاك المستخدم بتلك المؤسسة تحديدًا.
     * تستدعي performRelease() مباشرة — راجع تعليق الفئة أعلاه لسبب عدم إعادة
     * فحص Authorization هنا.
     */
    public function releaseAllForUserInOrganization(User $actor, int $userId, int $organizationId): void
    {
        $seats = SubscriptionSeat::query()
            ->where('user_id', $userId)
            ->whereHas('subscription', function ($query) use ($organizationId) {
                $query->where('subscriber_type', 'organization')->where('subscriber_id', $organizationId);
            })
            ->active()
            ->get();

        foreach ($seats as $seat) {
            $this->performRelease($actor, $seat);
        }
    }

    private function performRelease(User $actor, SubscriptionSeat $seat): void
    {
        DB::transaction(function () use ($actor, $seat) {
            $subscription = $seat->subscription;
            $organization = $subscription->subscriber;

            // BR-2B-03 — إبطال فوري، بلا فترة سماح.
            $seat->update(['status' => 'released', 'released_at' => now()]);
            $this->log($actor, AuditEvent::SeatReleased, $seat, $organization);

            $access = $subscription->accessAssignments()->where('user_id', $seat->user_id)->active()->first();
            if ($access) {
                $access->update(['status' => 'revoked', 'revoked_at' => now()]);
                $this->log($actor, AuditEvent::AccessRevoked, $access, $organization);
            }
        });
    }

    private function log(User $actor, AuditEvent $event, $subject, $organization): void
    {
        AuditLog::create([
            'organization_id' => $organization?->id,
            'actor_user_id' => $actor->id,
            'event' => $event->value,
            'subject_type' => $subject::class,
            'subject_id' => $subject->id,
            'metadata' => null,
        ]);
    }
}
