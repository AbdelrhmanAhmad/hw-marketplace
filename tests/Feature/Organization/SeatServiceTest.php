<?php

namespace Tests\Feature\Organization;

use App\Enums\AuditEvent;
use App\Enums\MembershipRole;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationSubscriptionService;
use App\Services\SeatService;
use Database\Seeders\MarketplaceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SeatServiceTest extends TestCase
{
    use RefreshDatabase;

    private function organizationWithSubscription(int $seatLimit = 5): array
    {
        $this->seed(MarketplaceCatalogSeeder::class);

        $owner = User::factory()->create();
        $organization = Organization::create(['name' => 'مكتب الاختبار', 'type' => 'firm', 'owner_id' => $owner->id]);
        Membership::create(['user_id' => $owner->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Owner]);

        $item = \App\Models\MarketplaceItem::where('key', 'marefa')->firstOrFail();
        $item->update(['billing_model' => 'both']);

        $subscription = app(OrganizationSubscriptionService::class)->create($owner, $organization, $item, 'Professional', $seatLimit);

        return [$owner, $organization, $subscription];
    }

    private function addMember(Organization $organization, MembershipRole $role = MembershipRole::Lawyer): User
    {
        $user = User::factory()->create();
        Membership::create(['user_id' => $user->id, 'organization_id' => $organization->id, 'role' => $role]);

        return $user;
    }

    public function test_assign_creates_seat_and_active_access(): void
    {
        [$owner, $organization, $subscription] = $this->organizationWithSubscription();
        $member = $this->addMember($organization);

        $seat = app(SeatService::class)->assign($owner, $subscription, $member);

        $this->assertSame('assigned', $seat->status);
        $this->assertTrue($subscription->accessAssignments()->where('user_id', $member->id)->active()->exists());
        $this->assertTrue(AuditLog::where('event', AuditEvent::SeatAssigned->value)->exists());
    }

    public function test_assign_rejects_non_member(): void
    {
        [$owner, $organization, $subscription] = $this->organizationWithSubscription();
        $stranger = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        app(SeatService::class)->assign($owner, $subscription, $stranger);
    }

    public function test_assign_is_idempotent_for_already_assigned_user(): void
    {
        [$owner, $organization, $subscription] = $this->organizationWithSubscription();
        $member = $this->addMember($organization);
        $service = app(SeatService::class);

        $first = $service->assign($owner, $subscription, $member);
        $second = $service->assign($owner, $subscription, $member);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $subscription->seats()->active()->count());
    }

    public function test_release_revokes_access_immediately(): void
    {
        [$owner, $organization, $subscription] = $this->organizationWithSubscription();
        $member = $this->addMember($organization);
        $service = app(SeatService::class);

        $seat = $service->assign($owner, $subscription, $member);
        $service->release($owner, $seat);

        $this->assertSame('released', $seat->fresh()->status);
        $this->assertFalse($subscription->accessAssignments()->where('user_id', $member->id)->active()->exists());
        $this->assertTrue(AuditLog::where('event', AuditEvent::SeatReleased->value)->exists());
        $this->assertTrue(AuditLog::where('event', AuditEvent::AccessRevoked->value)->exists());
    }

    public function test_reassign_moves_seat_from_one_user_to_another(): void
    {
        [$owner, $organization, $subscription] = $this->organizationWithSubscription();
        $memberA = $this->addMember($organization);
        $memberB = $this->addMember($organization);
        $service = app(SeatService::class);

        $seat = $service->assign($owner, $subscription, $memberA);
        $service->reassign($owner, $seat, $memberB);

        $this->assertFalse($subscription->accessAssignments()->where('user_id', $memberA->id)->active()->exists());
        $this->assertTrue($subscription->accessAssignments()->where('user_id', $memberB->id)->active()->exists());
    }

    public function test_cannot_exceed_seat_limit(): void
    {
        [$owner, $organization, $subscription] = $this->organizationWithSubscription(seatLimit: 1);
        $memberA = $this->addMember($organization);
        $memberB = $this->addMember($organization);
        $service = app(SeatService::class);

        $service->assign($owner, $subscription, $memberA);

        $this->expectException(InvalidArgumentException::class);
        $service->assign($owner, $subscription, $memberB);
    }

    /**
     * BR-2B-07 — يثبت إن التحقق من الحد يعيد القراءة داخل القفل (لا Cache
     * بالذاكرة)، لا يعتمد على حالة مُحمَّلة مسبقًا بمعزل عن قاعدة البيانات.
     * تحدي اختبار Concurrency الحقيقي (طلبان فعليان متزامنان) موثَّق
     * ومُنفَّذ بمعزل عن PHPUnit — راجع تقرير Phase 2B قسم Concurrency.
     */
    public function test_seat_limit_check_reflects_freshly_committed_state_not_stale_instance(): void
    {
        [$owner, $organization, $subscription] = $this->organizationWithSubscription(seatLimit: 1);
        $memberA = $this->addMember($organization);
        $memberB = $this->addMember($organization);
        $service = app(SeatService::class);

        $service->assign($owner, $subscription, $memberA);

        // نموذج $subscription بذاكرة PHP لا يعرف عن التعيين أعلاه إلا لو
        // أُعيد تحميله — الخدمة نفسها تُعيد الاستعلام داخليًا (lockForUpdate)
        // لا تثق بالنموذج المُمرَّر، لذلك الرفض يحدث رغم تمرير نفس الكائن.
        $this->expectException(InvalidArgumentException::class);
        $service->assign($owner, $subscription, $memberB);
    }
}
