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

        // "network" لا يزال Coming Soon حقيقيًا (آخر عنصر متبقٍّ) — لا Backend خلفه.
        $network = (new DatabaseMarketplaceRepository)->find('network');

        $this->assertSame('soon', $network['status']);
        $this->assertArrayNotHasKey('href', $network);
    }

    /** Final Execution Sprint (Phase 4) — إفلاس تك انتقل من Catalog Item لتطبيق حقيقي. */
    public function test_database_repository_marks_bankruptcy_tech_as_available_with_href(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);

        $bankruptcyTech = (new DatabaseMarketplaceRepository)->find('bankruptcy-tech');

        $this->assertSame('available', $bankruptcyTech['status']);
        $this->assertArrayHasKey('href', $bankruptcyTech);
        $this->assertTrue($bankruptcyTech['free']);
        $this->assertStringContainsString('bankruptcy-tech', $bankruptcyTech['href']);
    }

    /** بوابة المقالات — انتقلت من Catalog Item لتطبيق حقيقي. */
    public function test_database_repository_marks_articles_as_available_with_href(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);

        $articles = (new DatabaseMarketplaceRepository)->find('articles');

        $this->assertSame('available', $articles['status']);
        $this->assertArrayHasKey('href', $articles);
        $this->assertTrue($articles['free']);
        $this->assertStringContainsString('articles', $articles['href']);
    }

    /** مجتمع الخدمات — انتقل من Catalog Item لتطبيق حقيقي. */
    public function test_database_repository_marks_community_as_available_with_href(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);

        $community = (new DatabaseMarketplaceRepository)->find('community');

        $this->assertSame('available', $community['status']);
        $this->assertArrayHasKey('href', $community);
        $this->assertTrue($community['free']);
        $this->assertStringContainsString('community', $community['href']);
    }

    /** بوابة التقنية — انتقلت من Catalog Item لتطبيق حقيقي. */
    public function test_database_repository_marks_tech_portal_as_available_with_href(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);

        $techPortal = (new DatabaseMarketplaceRepository)->find('tech-portal');

        $this->assertSame('available', $techPortal['status']);
        $this->assertArrayHasKey('href', $techPortal);
        // ليس "مجاني" — دخول البوابة بلا رسوم، لكن كل خدمة بعينها تُدفَع
        // داخل التطبيق (أول عنصر حقيقي بنموذج in_app_purchase).
        $this->assertFalse($techPortal['free']);
        $this->assertTrue($techPortal['in_app_purchase']);
        $this->assertStringContainsString('tech-portal', $techPortal['href']);
    }

    /** بوابة التدريب التعاوني — انتقلت من Catalog Item لتطبيق حقيقي. */
    public function test_database_repository_marks_internships_as_available_with_href(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);

        $internships = (new DatabaseMarketplaceRepository)->find('internships');

        $this->assertSame('available', $internships['status']);
        $this->assertArrayHasKey('href', $internships);
        $this->assertTrue($internships['free']);
        $this->assertStringContainsString('internships', $internships['href']);
    }

    /** محرك مسودة القضية الذكي — انتقل من Catalog Item لتطبيق حقيقي (آخر عنصر بالكتالوج). */
    public function test_database_repository_marks_ai_case_draft_as_available_with_href(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);

        $aiCaseDraft = (new DatabaseMarketplaceRepository)->find('ai-case-draft');

        $this->assertSame('available', $aiCaseDraft['status']);
        $this->assertArrayHasKey('href', $aiCaseDraft);
        $this->assertTrue($aiCaseDraft['free']);
        $this->assertStringContainsString('bankruptcy-tech', $aiCaseDraft['href']);
    }
}
