<?php

namespace Tests\Feature\Marketplace;

use App\Models\AccessAssignment;
use App\Models\MarketplaceItem;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\MarketplaceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * يغطي المسارات الأربعة المطلوبة صراحة: Free App، Duplicate، Cancel، Unauthorized.
 */
class AccessFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_app_end_to_end_flow(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $user = User::factory()->create();
        $item = MarketplaceItem::where('key', 'marefa')->firstOrFail();

        // 1) User بدون subscription → يرى التطبيق
        $show = $this->actingAs($user)->get('/marketplace/marefa');
        $show->assertOk();
        $show->assertSee('فعّل وادخل الآن');

        // 2) يستطيع الاشتراك/التفعيل → يحصل على Access
        $activate = $this->actingAs($user)->post('/marketplace/marefa/activate');
        $activate->assertRedirect();

        $this->assertDatabaseHas('subscriptions', [
            'subscriber_type' => 'user',
            'subscriber_id' => $user->id,
            'marketplace_item_id' => $item->id,
            'status' => 'active',
        ]);
        $this->assertTrue(
            AccessAssignment::whereHas('subscription', fn ($q) => $q->where('marketplace_item_id', $item->id))
                ->where('user_id', $user->id)
                ->active()
                ->exists()
        );

        // 3) يظهر في My Apps
        $myApps = $this->actingAs($user)->get('/my/apps');
        $myApps->assertOk();
        $myApps->assertSee('بوابة معرفة');

        // 4) يستطيع فتح التطبيق (البادج يعكس الحالة الحقيقية الآن)
        $showAfter = $this->actingAs($user)->get('/marketplace/marefa');
        $showAfter->assertOk();
        $showAfter->assertSee('ادخل إلى التطبيق');
    }

    public function test_duplicate_activation_does_not_create_a_second_subscription(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)->post('/marketplace/marefa/activate');
        $this->actingAs($user)->post('/marketplace/marefa/activate');

        $item = MarketplaceItem::where('key', 'marefa')->firstOrFail();
        $this->assertSame(
            1,
            Subscription::where('subscriber_type', 'user')
                ->where('subscriber_id', $user->id)
                ->where('marketplace_item_id', $item->id)
                ->count()
        );
    }

    public function test_cancel_flow_revokes_access_and_removes_from_my_apps(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)->post('/marketplace/marefa/activate');
        $this->actingAs($user)->get('/my/apps')->assertSee('بوابة معرفة');

        $cancel = $this->actingAs($user)->post('/marketplace/marefa/cancel');
        $cancel->assertRedirect(route('my-apps.index'));

        $this->assertDatabaseHas('subscriptions', ['status' => 'cancelled']);

        $myAppsAfter = $this->actingAs($user)->get('/my/apps');
        $myAppsAfter->assertOk();
        $myAppsAfter->assertSee('ما عندك أي تطبيق مفعّل بعد');
    }

    public function test_guest_cannot_activate_without_login(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);

        $response = $this->post('/marketplace/marefa/activate');

        $response->assertRedirect('/login');
        $this->assertDatabaseCount('subscriptions', 0);
    }

    /**
     * Unauthorized — الـbackend يمنع تجاوز الـMarketplace CTA، لا الواجهة فقط.
     * ملاحظة تفسير: بوابة معرفة نفسها تبقى عامة بلا تسجيل دخول إلزامي
     * (قرار قائم من مرحلة Core Platform Phase 1، لم يتغيّر بـPhase 1b) —
     * الاختبار هنا يتحقق من أن نقطة التفعيل نفسها (activate) ترفض أي
     * محاولة تفعيل لعنصر غير مؤهَّل للاشتراك الذاتي، بصرف النظر عن واجهة
     * المستخدم (حتى لو أُرسِل الطلب مباشرة بالـRoute بلا مرور بالزر).
     */
    public function test_backend_rejects_activation_of_non_free_item_even_without_ui_button(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $user = User::factory()->create();
        $item = MarketplaceItem::where('key', 'marefa')->firstOrFail();
        $item->update(['pricing_model' => null]);

        $response = $this->actingAs($user)->post('/marketplace/marefa/activate');

        $response->assertForbidden();
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_backend_rejects_activation_of_organization_only_item(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $user = User::factory()->create();
        $item = MarketplaceItem::where('key', 'marefa')->firstOrFail();
        $item->update(['billing_model' => 'organization_only']);

        $response = $this->actingAs($user)->post('/marketplace/marefa/activate');

        $response->assertForbidden();
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_my_apps_only_lists_items_user_has_active_access_to(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/my/apps');

        $response->assertOk();
        $response->assertSee('ما عندك أي تطبيق مفعّل بعد');
    }
}
