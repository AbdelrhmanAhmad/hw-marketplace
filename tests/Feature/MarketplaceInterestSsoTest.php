<?php

namespace Tests\Feature;

use App\Models\MarketplaceItem;
use App\Models\User;
use Database\Seeders\MarketplaceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketplaceInterestSsoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.core.url' => 'http://localhost:8001',
            'services.core.sso.client_id' => 'hw-marketplace',
            'services.core.sso.client_secret' => 'test-secret',
            'services.core.sso.redirect_uri' => 'http://localhost:8000/auth/core/callback',
        ]);

        $this->seed(MarketplaceCatalogSeeder::class);
    }

    public function test_authenticated_linked_user_can_record_interest_via_core_api(): void
    {
        $item = MarketplaceItem::query()->where('status', 'soon')->first()
            ?? MarketplaceItem::query()->firstOrFail();

        $user = User::factory()->create([
            'core_user_id' => 55,
            'is_platform_staff' => false,
        ]);

        Http::fake([
            'http://localhost:8001/api/integrations/marketplace/interests' => Http::response([
                'interest' => [
                    'id' => 1,
                    'user_id' => 55,
                    'marketplace_key' => $item->key,
                    'marketplace_name' => $item->name,
                    'status' => 'interested',
                ],
            ], 201),
        ]);

        $this->actingAs($user)
            ->post(route('platform.marketplace.interest', $item->key))
            ->assertRedirect(route('platform.marketplace.show', $item->key))
            ->assertSessionHas('interest_success');

        Http::assertSent(function ($request) use ($item) {
            return str_contains($request->url(), '/api/integrations/marketplace/interests')
                && $request['core_user_id'] === 55
                && $request['marketplace_key'] === $item->key;
        });
    }

    public function test_interest_requires_core_user_id(): void
    {
        $item = MarketplaceItem::query()->firstOrFail();
        $user = User::factory()->create([
            'core_user_id' => null,
            'is_platform_staff' => false,
        ]);

        $this->actingAs($user)
            ->post(route('platform.marketplace.interest', $item->key))
            ->assertRedirect(route('platform.marketplace.show', $item->key))
            ->assertSessionHasErrors('interest');
    }

    public function test_interest_does_not_create_subscription(): void
    {
        $item = MarketplaceItem::query()->where('key', 'marefa')->firstOrFail();
        $user = User::factory()->create([
            'core_user_id' => 10,
            'is_platform_staff' => false,
        ]);

        Http::fake([
            'http://localhost:8001/api/integrations/marketplace/interests' => Http::response([
                'interest' => [
                    'id' => 1,
                    'user_id' => 10,
                    'marketplace_key' => $item->key,
                    'marketplace_name' => $item->name,
                    'status' => 'interested',
                ],
            ], 201),
        ]);

        $this->actingAs($user)->post(route('platform.marketplace.interest', $item->key));

        $this->assertFalse($user->fresh()->hasActiveSubscription($item->key));
        $this->assertSame(0, $user->marketplaceSubscriptions()->count());
    }

    public function test_activate_still_works_independently_of_interest(): void
    {
        $item = MarketplaceItem::query()->where('key', 'marefa')->firstOrFail();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('platform.marketplace.activate', 'marefa'))
            ->assertRedirect();

        $this->assertTrue(
            $user->fresh()->marketplaceSubscriptions()->where('marketplace_item_id', $item->id)->active()->exists()
        );
    }
}
