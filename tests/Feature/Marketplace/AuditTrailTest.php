<?php

namespace Tests\Feature\Marketplace;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\MarketplaceItem;
use App\Models\User;
use App\Services\SubscriptionService;
use Database\Seeders\MarketplaceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_activation_writes_created_activated_and_access_granted_events(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $user = User::factory()->create();
        $item = MarketplaceItem::where('key', 'marefa')->firstOrFail();

        app(SubscriptionService::class)->subscribeUserToFreeItem($user, $item);

        $events = AuditLog::where('actor_user_id', $user->id)->pluck('event')->all();

        $this->assertContains(AuditEvent::SubscriptionCreated->value, $events);
        $this->assertContains(AuditEvent::SubscriptionActivated->value, $events);
        $this->assertContains(AuditEvent::AccessGranted->value, $events);
    }

    public function test_cancellation_writes_cancelled_and_access_revoked_events(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $user = User::factory()->create();
        $item = MarketplaceItem::where('key', 'marefa')->firstOrFail();
        $service = app(SubscriptionService::class);

        $subscription = $service->subscribeUserToFreeItem($user, $item);
        $service->cancel($subscription->fresh());

        $events = AuditLog::where('actor_user_id', $user->id)->pluck('event')->all();

        $this->assertContains(AuditEvent::SubscriptionCancelled->value, $events);
        $this->assertContains(AuditEvent::AccessRevoked->value, $events);
    }

    public function test_audit_log_entries_carry_no_organization_context_in_phase_1b(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $user = User::factory()->create();
        $item = MarketplaceItem::where('key', 'marefa')->firstOrFail();

        app(SubscriptionService::class)->subscribeUserToFreeItem($user, $item);

        $this->assertTrue(AuditLog::where('actor_user_id', $user->id)->whereNull('organization_id')->exists());
    }

    public function test_audit_log_cannot_be_updated(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $user = User::factory()->create();
        $item = MarketplaceItem::where('key', 'marefa')->firstOrFail();
        app(SubscriptionService::class)->subscribeUserToFreeItem($user, $item);

        $log = AuditLog::first();

        $this->expectException(LogicException::class);
        $log->update(['event' => 'tampered']);
    }

    public function test_audit_log_cannot_be_deleted(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $user = User::factory()->create();
        $item = MarketplaceItem::where('key', 'marefa')->firstOrFail();
        app(SubscriptionService::class)->subscribeUserToFreeItem($user, $item);

        $log = AuditLog::first();

        $this->expectException(LogicException::class);
        $log->delete();
    }
}
