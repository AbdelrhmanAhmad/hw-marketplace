<?php

namespace Tests\Feature\Organization;

use App\Enums\AuditEvent;
use App\Enums\MembershipRole;
use App\Models\AuditLog;
use App\Models\MarketplaceItem;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationSubscriptionService;
use App\Services\SeatService;
use Database\Seeders\MarketplaceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class OrganizationAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private function setupOrganization(): array
    {
        $this->seed(MarketplaceCatalogSeeder::class);

        $owner = User::factory()->create();
        $organization = Organization::create(['name' => 'مكتب الاختبار', 'type' => 'firm', 'owner_id' => $owner->id]);
        Membership::create(['user_id' => $owner->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Owner]);

        $member = User::factory()->create();
        Membership::create(['user_id' => $member->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);

        $item = MarketplaceItem::where('key', 'marefa')->firstOrFail();
        $item->update(['billing_model' => 'both']);

        return [$owner, $organization, $member, $item];
    }

    public function test_seat_events_are_logged_with_organization_id_populated(): void
    {
        [$owner, $organization, $member, $item] = $this->setupOrganization();

        $subscription = app(OrganizationSubscriptionService::class)->create($owner, $organization, $item, 'Professional', 5);
        app(SeatService::class)->assign($owner, $subscription, $member);

        $log = AuditLog::where('event', AuditEvent::SeatAssigned->value)->firstOrFail();

        $this->assertSame($organization->id, $log->organization_id);
    }

    public function test_personal_events_still_carry_no_organization_context(): void
    {
        [$owner, $organization, $member, $item] = $this->setupOrganization();

        app(\App\Services\SubscriptionService::class)->subscribeUserToFreeItem($member, $item);

        $log = AuditLog::where('actor_user_id', $member->id)
            ->where('event', AuditEvent::SubscriptionCreated->value)
            ->firstOrFail();

        $this->assertNull($log->organization_id);
    }

    public function test_organization_scoped_audit_log_still_cannot_be_updated_or_deleted(): void
    {
        [$owner, $organization, $member, $item] = $this->setupOrganization();
        $subscription = app(OrganizationSubscriptionService::class)->create($owner, $organization, $item, 'Professional', 5);
        app(SeatService::class)->assign($owner, $subscription, $member);

        $log = AuditLog::where('event', AuditEvent::SeatAssigned->value)->firstOrFail();

        $this->expectException(LogicException::class);
        $log->update(['event' => 'tampered']);
    }
}
