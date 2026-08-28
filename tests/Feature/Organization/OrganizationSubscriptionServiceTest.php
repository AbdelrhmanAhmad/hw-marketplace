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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class OrganizationSubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function organizationOwner(): array
    {
        $this->seed(MarketplaceCatalogSeeder::class);

        $owner = User::factory()->create();
        $organization = Organization::create(['name' => 'مكتب الاختبار', 'type' => 'firm', 'owner_id' => $owner->id]);
        Membership::create(['user_id' => $owner->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Owner]);

        $item = \App\Models\MarketplaceItem::where('key', 'marefa')->firstOrFail();
        $item->update(['billing_model' => 'both']);

        return [$owner, $organization, $item];
    }

    public function test_creates_organization_subscription_with_dedicated_plan(): void
    {
        [$owner, $organization, $item] = $this->organizationOwner();

        $subscription = app(OrganizationSubscriptionService::class)->create($owner, $organization, $item, 'Professional', 5);

        $this->assertSame('organization', $subscription->subscriber_type);
        $this->assertSame($organization->id, $subscription->subscriber_id);
        $this->assertSame(5, $subscription->plan->seat_limit);
        $this->assertTrue(
            AuditLog::where('event', AuditEvent::SubscriptionCreated->value)
                ->where('organization_id', $organization->id)
                ->exists()
        );
    }

    public function test_rejects_subscription_for_item_not_supporting_organization_billing(): void
    {
        [$owner, $organization, $item] = $this->organizationOwner();
        $item->update(['billing_model' => 'user_only']);

        $this->expectException(InvalidArgumentException::class);

        app(OrganizationSubscriptionService::class)->create($owner, $organization, $item, 'Professional', 5);
    }

    public function test_creating_duplicate_subscription_returns_existing_record(): void
    {
        [$owner, $organization, $item] = $this->organizationOwner();
        $service = app(OrganizationSubscriptionService::class);

        $first = $service->create($owner, $organization, $item, 'Professional', 5);
        $second = $service->create($owner, $organization, $item, 'Professional', 5);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $organization->marketplaceSubscriptions()->count());
    }

    public function test_can_increase_seat_limit(): void
    {
        [$owner, $organization, $item] = $this->organizationOwner();
        $service = app(OrganizationSubscriptionService::class);

        $subscription = $service->create($owner, $organization, $item, 'Professional', 5);
        $service->changeSeatLimit($owner, $subscription, 10);

        $this->assertSame(10, $subscription->plan->fresh()->seat_limit);
    }

    public function test_rejects_seat_limit_reduction_below_active_seat_count(): void
    {
        [$owner, $organization, $item] = $this->organizationOwner();
        $service = app(OrganizationSubscriptionService::class);
        $seats = app(SeatService::class);

        $subscription = $service->create($owner, $organization, $item, 'Professional', 5);

        $memberA = User::factory()->create();
        Membership::create(['user_id' => $memberA->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);
        $seats->assign($owner, $subscription, $memberA);

        $this->expectException(InvalidArgumentException::class);
        $service->changeSeatLimit($owner, $subscription, 0);
    }

    public function test_cancel_releases_all_seats_and_revokes_access(): void
    {
        [$owner, $organization, $item] = $this->organizationOwner();
        $service = app(OrganizationSubscriptionService::class);
        $seats = app(SeatService::class);

        $subscription = $service->create($owner, $organization, $item, 'Professional', 5);
        $member = User::factory()->create();
        Membership::create(['user_id' => $member->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);
        $seat = $seats->assign($owner, $subscription, $member);

        $service->cancel($owner, $subscription);

        $this->assertSame('cancelled', $subscription->fresh()->status);
        $this->assertSame('released', $seat->fresh()->status);
        $this->assertFalse($subscription->accessAssignments()->where('user_id', $member->id)->active()->exists());
    }

    /**
     * Platform Authorization Foundation — Service-level Authorization،
     * بمعزل تام عن Filament (استدعاء مباشر). راجع
     * docs/platform-authorization-foundation-specification.md §8.
     */
    public function test_customer_with_no_membership_cannot_create_subscription(): void
    {
        [, $organization, $item] = $this->organizationOwner();
        $customer = User::factory()->create();

        $this->expectException(AuthorizationException::class);
        app(OrganizationSubscriptionService::class)->create($customer, $organization, $item, 'Professional', 5);
    }

    public function test_customer_with_no_membership_cannot_change_seat_limit(): void
    {
        [$owner, $organization, $item] = $this->organizationOwner();
        $service = app(OrganizationSubscriptionService::class);
        $subscription = $service->create($owner, $organization, $item, 'Professional', 5);
        $customer = User::factory()->create();

        $this->expectException(AuthorizationException::class);
        $service->changeSeatLimit($customer, $subscription, 10);
    }

    public function test_customer_with_no_membership_cannot_cancel_subscription(): void
    {
        [$owner, $organization, $item] = $this->organizationOwner();
        $service = app(OrganizationSubscriptionService::class);
        $subscription = $service->create($owner, $organization, $item, 'Professional', 5);
        $customer = User::factory()->create();

        $this->expectException(AuthorizationException::class);
        $service->cancel($customer, $subscription);
    }

    public function test_member_of_another_organization_cannot_manage_unrelated_subscription(): void
    {
        [$owner, $organization, $item] = $this->organizationOwner();
        $service = app(OrganizationSubscriptionService::class);
        $subscription = $service->create($owner, $organization, $item, 'Professional', 5);

        $otherOrg = Organization::create(['name' => 'مكتب آخر', 'type' => 'firm', 'owner_id' => $owner->id]);
        $outsiderAdmin = User::factory()->create();
        Membership::create(['user_id' => $outsiderAdmin->id, 'organization_id' => $otherOrg->id, 'role' => MembershipRole::Admin]);

        $this->expectException(AuthorizationException::class);
        $service->changeSeatLimit($outsiderAdmin, $subscription, 10);
    }

    public function test_platform_staff_with_no_membership_can_manage_any_organization_subscription(): void
    {
        [$owner, $organization, $item] = $this->organizationOwner();
        $staff = User::factory()->create(['is_platform_staff' => true]);
        $service = app(OrganizationSubscriptionService::class);

        $subscription = $service->create($staff, $organization, $item, 'Professional', 5);
        $service->changeSeatLimit($staff, $subscription, 10);
        $service->cancel($staff, $subscription);

        $this->assertSame(10, $subscription->plan->fresh()->seat_limit);
        $this->assertSame('cancelled', $subscription->fresh()->status);
        $this->assertFalse(
            Membership::where('user_id', $staff->id)->where('organization_id', $organization->id)->exists()
        );
        $this->assertFalse($owner->is($staff));
    }
}
