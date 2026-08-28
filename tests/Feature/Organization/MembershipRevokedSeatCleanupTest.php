<?php

namespace Tests\Feature\Organization;

use App\Enums\MembershipRole;
use App\Models\MarketplaceItem;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationSubscriptionService;
use App\Services\SeatService;
use Database\Seeders\MarketplaceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BR-2B-04 — أول اختبار حقيقي لمستهلك MembershipRevoked.
 */
class MembershipRevokedSeatCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_removing_membership_releases_seat_and_revokes_access_but_keeps_subscription(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);

        $owner = User::factory()->create();
        $organization = Organization::create(['name' => 'مكتب الاختبار', 'type' => 'firm', 'owner_id' => $owner->id]);
        Membership::create(['user_id' => $owner->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Owner]);

        $member = User::factory()->create();
        $membership = Membership::create(['user_id' => $member->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);

        $item = MarketplaceItem::where('key', 'marefa')->firstOrFail();
        $item->update(['billing_model' => 'both']);

        $subscription = app(OrganizationSubscriptionService::class)->create($owner, $organization, $item, 'Professional', 5);
        $seat = app(SeatService::class)->assign($owner, $subscription, $member);

        $this->actingAs($owner);
        $membership->delete();

        $this->assertSame('released', $seat->fresh()->status);
        $this->assertFalse($subscription->accessAssignments()->where('user_id', $member->id)->active()->exists());
        // الاشتراك المؤسسي نفسه يبقى فعّالًا تمامًا — لا يتأثر بمغادرة عضو واحد.
        $this->assertSame('active', $subscription->fresh()->status);
    }

    public function test_other_members_seats_are_not_affected_by_one_member_leaving(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);

        $owner = User::factory()->create();
        $organization = Organization::create(['name' => 'مكتب الاختبار', 'type' => 'firm', 'owner_id' => $owner->id]);
        Membership::create(['user_id' => $owner->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Owner]);

        $memberA = User::factory()->create();
        $memberB = User::factory()->create();
        $membershipA = Membership::create(['user_id' => $memberA->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);
        Membership::create(['user_id' => $memberB->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);

        $item = MarketplaceItem::where('key', 'marefa')->firstOrFail();
        $item->update(['billing_model' => 'both']);

        $subscription = app(OrganizationSubscriptionService::class)->create($owner, $organization, $item, 'Professional', 5);
        $seatService = app(SeatService::class);
        $seatService->assign($owner, $subscription, $memberA);
        $seatB = $seatService->assign($owner, $subscription, $memberB);

        $this->actingAs($owner);
        $membershipA->delete();

        $this->assertSame('assigned', $seatB->fresh()->status);
        $this->assertTrue($subscription->accessAssignments()->where('user_id', $memberB->id)->active()->exists());
    }
}
