<?php

namespace Tests\Feature\Marketplace;

use App\Enums\AccessReason;
use App\Models\MarketplaceItem;
use App\Models\User;
use App\Services\EntitlementResolver;
use App\Services\SubscriptionService;
use Database\Seeders\MarketplaceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntitlementResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_needs_subscription_when_no_subscription_exists(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $user = User::factory()->create();
        $item = MarketplaceItem::where('key', 'marefa')->firstOrFail();

        $decision = app(EntitlementResolver::class)->resolve($user, $item);

        $this->assertFalse($decision->allowed);
        $this->assertSame(AccessReason::NeedsSubscription, $decision->reason);
    }

    public function test_returns_has_access_after_subscribing(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $user = User::factory()->create();
        $item = MarketplaceItem::where('key', 'marefa')->firstOrFail();

        app(SubscriptionService::class)->subscribeUserToFreeItem($user, $item);

        $decision = app(EntitlementResolver::class)->resolve($user, $item);

        $this->assertTrue($decision->allowed);
        $this->assertSame(AccessReason::HasAccess, $decision->reason);
    }

    public function test_returns_needs_subscription_again_after_cancellation(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $user = User::factory()->create();
        $item = MarketplaceItem::where('key', 'marefa')->firstOrFail();
        $service = app(SubscriptionService::class);

        $subscription = $service->subscribeUserToFreeItem($user, $item);
        $service->cancel($subscription->fresh());

        $decision = app(EntitlementResolver::class)->resolve($user, $item);

        $this->assertFalse($decision->allowed);
        $this->assertSame(AccessReason::NeedsSubscription, $decision->reason);
    }

    public function test_returns_item_unavailable_for_non_published_item(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $user = User::factory()->create();
        $item = MarketplaceItem::where('key', 'marefa')->firstOrFail();
        $item->update(['status' => 'suspended']);

        $decision = app(EntitlementResolver::class)->resolve($user, $item);

        $this->assertFalse($decision->allowed);
        $this->assertSame(AccessReason::ItemUnavailable, $decision->reason);
    }
}
