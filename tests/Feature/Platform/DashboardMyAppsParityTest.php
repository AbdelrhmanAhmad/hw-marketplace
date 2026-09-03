<?php

namespace Tests\Feature\Platform;

use App\Models\AppSubscription;
use App\Models\MarketplaceItem;
use App\Models\User;
use App\Services\SubscriptionService;
use Database\Seeders\MarketplaceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Final Execution Sprint — يثبت إغلاق التعارض الحي المكتشَف بـ
 * `marketplace:subscription-parity-check`: Dashboard وMy Apps يعرضان نفس
 * الحقيقة دائمًا (كلاهما عبر UserAppsResolver)، ولا أحد منهما يعتمد على
 * `app_subscriptions` (القديم) بعد الآن.
 */
class DashboardMyAppsParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_and_my_apps_show_identical_apps_for_active_subscription(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $user = User::factory()->create();
        $item = MarketplaceItem::where('key', 'marefa')->firstOrFail();
        app(SubscriptionService::class)->subscribeUserToFreeItem($user, $item);

        $dashboard = $this->actingAs($user)->get('/dashboard');
        $myApps = $this->actingAs($user)->get('/my/apps');

        $dashboard->assertOk()->assertSee('بوابة معرفة');
        $myApps->assertOk()->assertSee('بوابة معرفة');
    }

    /**
     * السيناريو المحوري المكتشَف فعليًا: صف قديم `active` بـ`app_subscriptions`
     * (لم يُلمَس، متروك تاريخيًا كما هو)، بينما المستخدم ألغى اشتراكه فعليًا
     * بالنظام الجديد. يجب أن يحترم كلا العرضين الإلغاء الحقيقي — لا أحد
     * يعتمد على السجل القديم إطلاقًا.
     */
    public function test_dashboard_and_my_apps_both_respect_real_cancellation_ignoring_stale_legacy_row(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $user = User::factory()->create();
        $item = MarketplaceItem::where('key', 'marefa')->firstOrFail();

        // صف Legacy نشط، يُترَك عمدًا بلا لمس (لا حذف، لا تعديل — كما طلب المستخدم).
        AppSubscription::create(['user_id' => $user->id, 'app_key' => 'marefa', 'status' => 'active', 'subscribed_at' => now()]);

        // اشتراك حقيقي بالنظام الجديد، ثم إلغاء صريح.
        $subscriptionService = app(SubscriptionService::class);
        $subscription = $subscriptionService->subscribeUserToFreeItem($user, $item);
        $subscriptionService->cancel($subscription);

        $dashboard = $this->actingAs($user)->get('/dashboard');
        $myApps = $this->actingAs($user)->get('/my/apps');

        // "بوابة معرفة" تظهر دائمًا بتذييل الصفحة (رابط ثابت) — الدليل الصحيح
        // هنا هو ظهور حالة "لا تطبيقات" تحديدًا، لا غياب النص العام بالصفحة.
        $dashboard->assertOk()->assertSee('ما عندك أي تطبيق مفعّل بعد');
        $myApps->assertOk()->assertSee('ما عندك أي تطبيق مفعّل بعد');

        // الصف القديم يبقى كما هو — لا حذف، لا تعديل (شفافية تاريخية).
        $this->assertSame('active', AppSubscription::where('user_id', $user->id)->first()->status);
    }

    /** Phase 7 — My Apps → إفلاس تك يعمل فعليًا بعد التفعيل، رابطه صحيح. */
    public function test_my_apps_shows_bankruptcy_tech_after_activation_with_correct_link(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $user = User::factory()->create();
        $item = MarketplaceItem::where('key', 'bankruptcy-tech')->firstOrFail();
        app(SubscriptionService::class)->subscribeUserToFreeItem($user, $item);

        $this->actingAs($user)->get('/my/apps')
            ->assertOk()
            ->assertSee('إفلاس تك')
            ->assertSee('apps/bankruptcy-tech', false);
    }

    public function test_dashboard_shows_empty_state_with_no_subscriptions(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('ما عندك أي تطبيق مفعّل بعد');
    }
}
