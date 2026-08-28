<?php

namespace Tests\Feature\Marketplace;

use App\Repositories\DatabaseMarketplaceRepository;
use App\Repositories\MarketplaceCatalogRepository;
use App\Repositories\StaticPlatformAppsRepository;
use Database\Seeders\MarketplaceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * يتحقق إن /marketplace و/marketplace/{key} يعملان بنفس السلوك تمامًا
 * بغض النظر عن أي تطبيق Repository مربوط خلف الواجهة — جوهر Compatibility Layer.
 */
class CompatibilityLayerTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketplace_index_works_with_static_repository_bound(): void
    {
        $this->app->bind(MarketplaceCatalogRepository::class, StaticPlatformAppsRepository::class);

        $response = $this->get('/marketplace');

        $response->assertOk();
        $response->assertSee('بوابة معرفة');
    }

    public function test_marketplace_index_works_with_database_repository_bound(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $this->app->bind(MarketplaceCatalogRepository::class, DatabaseMarketplaceRepository::class);

        $response = $this->get('/marketplace');

        $response->assertOk();
        $response->assertSee('بوابة معرفة');
    }

    public function test_marketplace_show_works_identically_with_both_sources(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);

        $this->app->bind(MarketplaceCatalogRepository::class, StaticPlatformAppsRepository::class);
        $staticResponse = $this->get('/marketplace/marefa');
        $staticResponse->assertOk();

        $this->app->bind(MarketplaceCatalogRepository::class, DatabaseMarketplaceRepository::class);
        $databaseResponse = $this->get('/marketplace/marefa');
        $databaseResponse->assertOk();

        $staticResponse->assertSee('بوابة معرفة');
        $databaseResponse->assertSee('بوابة معرفة');
    }

    public function test_unknown_key_returns_404_with_both_sources(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);

        $this->app->bind(MarketplaceCatalogRepository::class, StaticPlatformAppsRepository::class);
        $this->get('/marketplace/does-not-exist')->assertNotFound();

        $this->app->bind(MarketplaceCatalogRepository::class, DatabaseMarketplaceRepository::class);
        $this->get('/marketplace/does-not-exist')->assertNotFound();
    }
}
