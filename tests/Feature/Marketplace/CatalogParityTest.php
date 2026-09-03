<?php

namespace Tests\Feature\Marketplace;

use App\Repositories\DatabaseMarketplaceRepository;
use App\Repositories\StaticPlatformAppsRepository;
use App\Support\PlatformApps;
use Database\Seeders\MarketplaceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BR-011: MarketplaceCatalogRepository::find()/all() يرجّعان نفس Shape البيانات
 * بغض النظر عن التطبيق الفعّال. هذا الاختبار هو المحك الحقيقي قبل أي Cutover
 * (مُنفَّذ ومُتحقَّق منه تاريخيًا). Final Execution Sprint — إفلاس تك تطبيق
 * حقيقي الآن، يتجاوز عمدًا اللقطة القديمة المجمَّدة (`status`/`free`) —
 * الأمر (`marketplace:catalog-parity-check`) يستثنيه صراحة (EVOLVED_ITEMS)،
 * لا يُخفي الفارق، فقط لا يُصنِّفه عطلًا.
 */
class CatalogParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_parity_check_command_passes_with_zero_mismatches(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);

        $this->artisan('marketplace:catalog-parity-check')
            ->assertExitCode(0);
    }

    public function test_all_eight_items_exist_in_both_sources(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);

        $static = (new StaticPlatformAppsRepository)->all()->keyBy('key');
        $database = (new DatabaseMarketplaceRepository)->all()->keyBy('key');

        $this->assertCount(8, PlatformApps::all());
        $this->assertCount(8, $static);
        $this->assertCount(8, $database);
        $this->assertEqualsCanonicalizing($static->keys()->all(), $database->keys()->all());
    }

    public function test_every_field_matches_field_by_field_for_every_item(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);

        $static = (new StaticPlatformAppsRepository)->all()->keyBy('key');
        $database = (new DatabaseMarketplaceRepository)->all()->keyBy('key');

        // إفلاس تك تجاوز اللقطة القديمة عمدًا (أصبح تطبيقًا حقيقيًا) —
        // status/free/href تتغيّر بالتصميم له تحديدًا، لا تُقارَن هنا.
        $evolvedItems = ['bankruptcy-tech'];

        foreach ($static as $key => $oldApp) {
            $newApp = $database->get($key);
            $isEvolved = in_array($key, $evolvedItems, true);

            $this->assertNotNull($newApp, "العنصر [{$key}] غير موجود بالمصدر الجديد");
            $this->assertSame($oldApp['name'], $newApp['name'], "اختلاف بالاسم لـ[{$key}]");
            $this->assertSame($oldApp['tagline'], $newApp['tagline'], "اختلاف بالـTagline لـ[{$key}]");
            $this->assertSame($oldApp['description'], $newApp['description'], "اختلاف بالوصف لـ[{$key}]");
            $this->assertSame($oldApp['icon'], $newApp['icon'], "اختلاف بالأيقونة لـ[{$key}]");
            $this->assertEqualsCanonicalizing(
                $oldApp['audiences'] ?? [],
                $newApp['audiences'],
                "اختلاف بالجمهور المستهدف لـ[{$key}]"
            );

            if ($isEvolved) {
                // التطوّر المتوقَّع نفسه: تحقّق صراحة أنه انتقل لـ"مُطلَق"، لا اختلاف عشوائي.
                $this->assertSame('available', $newApp['status'], "[{$key}] يُفترَض يكون available بعد الإطلاق");
                $this->assertTrue($newApp['free'], "[{$key}] يُفترَض يكون free بعد الإطلاق");
                $this->assertArrayHasKey('href', $newApp, "[{$key}] يُفترَض يملك href بعد الإطلاق");

                continue;
            }

            $this->assertSame($oldApp['status'], $newApp['status'], "اختلاف بالحالة لـ[{$key}]");
            $this->assertSame($oldApp['free'] ?? false, $newApp['free'], "اختلاف بحقل free لـ[{$key}]");

            if (isset($oldApp['href'])) {
                $this->assertSame($oldApp['href'], $newApp['href'] ?? null, "اختلاف بالرابط لـ[{$key}]");
            } else {
                $this->assertArrayNotHasKey('href', $newApp, "رابط غير متوقَّع لـ[{$key}]");
            }
        }
    }
}
