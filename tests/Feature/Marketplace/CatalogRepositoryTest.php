<?php

namespace Tests\Feature\Marketplace;

use App\Repositories\DatabaseMarketplaceRepository;
use App\Repositories\StaticPlatformAppsRepository;
use App\Support\PlatformApps;
use Database\Seeders\MarketplaceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_static_repository_returns_all_platform_apps(): void
    {
        $repository = new StaticPlatformAppsRepository;

        $this->assertCount(count(PlatformApps::all()), $repository->all());
        $this->assertSame('marefa', $repository->all()->first()['key']);
    }

    public function test_static_repository_finds_by_key(): void
    {
        $repository = new StaticPlatformAppsRepository;

        $this->assertSame('بوابة معرفة', $repository->find('marefa')['name']);
        $this->assertNull($repository->find('does-not-exist'));
    }

    public function test_database_repository_returns_seeded_items(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);

        $repository = new DatabaseMarketplaceRepository;

        $this->assertCount(count(PlatformApps::all()), $repository->all());
    }

    public function test_database_repository_marks_marefa_as_available_with_href(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);

        $marefa = (new DatabaseMarketplaceRepository)->find('marefa');

        $this->assertSame('available', $marefa['status']);
        $this->assertArrayHasKey('href', $marefa);
        $this->assertTrue($marefa['free']);
    }

    public function test_database_repository_marks_unlaunched_apps_as_soon_without_href(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);

        $bankruptcyTech = (new DatabaseMarketplaceRepository)->find('bankruptcy-tech');

        $this->assertSame('soon', $bankruptcyTech['status']);
        $this->assertArrayNotHasKey('href', $bankruptcyTech);
        $this->assertFalse($bankruptcyTech['free']);
    }
}
