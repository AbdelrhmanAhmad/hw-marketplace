<?php

namespace Tests\Feature\Marketplace;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\MarketplaceItem;
use App\Models\User;
use App\Services\SubscriptionService;
use Database\Seeders\MarketplaceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function marefa(): MarketplaceItem
    {
        $this->seed(MarketplaceCatalogSeeder::class);

        return MarketplaceItem::where('key', 'marefa')->firstOrFail();
    }

    public function test_creates_subscription_and_access_for_free_item(): void
    {
        $user = User::factory()->create();
        $item = $this->marefa();

        $subscription = app(SubscriptionService::class)->subscribeUserToFreeItem($user, $item);

        $this->assertSame('active', $subscription->status);
        $this->assertSame('user', $subscription->subscriber_type);
        $this->assertSame($user->id, $subscription->subscriber_id);
        $this->assertTrue($subscription->accessAssignments()->active()->exists());
    }

    public function test_does_not_create_duplicate_subscription_for_same_user_and_item(): void
    {
        $user = User::factory()->create();
        $item = $this->marefa();
        $service = app(SubscriptionService::class);

        $first = $service->subscribeUserToFreeItem($user, $item);
        $second = $service->subscribeUserToFreeItem($user, $item);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $user->marketplaceSubscriptions()->count());
        $this->assertSame(1, $first->accessAssignments()->count());
    }

    public function test_duplicate_activation_writes_no_extra_audit_entries(): void
    {
        $user = User::factory()->create();
        $item = $this->marefa();
        $service = app(SubscriptionService::class);

        $service->subscribeUserToFreeItem($user, $item);
        $countAfterFirst = AuditLog::count();

        $service->subscribeUserToFreeItem($user, $item);
        $countAfterSecond = AuditLog::count();

        $this->assertSame($countAfterFirst, $countAfterSecond);
    }

    public function test_reactivates_a_cancelled_subscription_instead_of_creating_a_new_row(): void
    {
        $user = User::factory()->create();
        $item = $this->marefa();
        $service = app(SubscriptionService::class);

        $subscription = $service->subscribeUserToFreeItem($user, $item);
        $service->cancel($subscription->fresh());

        $reactivated = $service->subscribeUserToFreeItem($user, $item);

        $this->assertSame($subscription->id, $reactivated->id);
        $this->assertSame('active', $reactivated->fresh()->status);
        $this->assertTrue($reactivated->accessAssignments()->active()->exists());
    }

    public function test_rejects_non_free_item(): void
    {
        $user = User::factory()->create();
        $item = $this->marefa();
        $item->update(['pricing_model' => null]);

        $this->expectException(InvalidArgumentException::class);

        app(SubscriptionService::class)->subscribeUserToFreeItem($user, $item);
    }

    public function test_rejects_organization_only_item(): void
    {
        $user = User::factory()->create();
        $item = $this->marefa();
        $item->update(['billing_model' => 'organization_only']);

        $this->expectException(InvalidArgumentException::class);

        app(SubscriptionService::class)->subscribeUserToFreeItem($user, $item);
    }

    public function test_cancel_revokes_access_and_marks_subscription_cancelled(): void
    {
        $user = User::factory()->create();
        $item = $this->marefa();
        $service = app(SubscriptionService::class);

        $subscription = $service->subscribeUserToFreeItem($user, $item);
        $service->cancel($subscription->fresh());

        $subscription->refresh();

        $this->assertSame('cancelled', $subscription->status);
        $this->assertFalse($subscription->accessAssignments()->active()->exists());
        $this->assertTrue(
            AuditLog::where('event', AuditEvent::SubscriptionCancelled->value)->exists()
        );
        $this->assertTrue(
            AuditLog::where('event', AuditEvent::AccessRevoked->value)->exists()
        );
    }
}
