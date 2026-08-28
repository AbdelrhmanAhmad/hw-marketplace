<?php

namespace Tests\Feature\Marketplace;

use App\Models\AppSubscription;
use App\Models\User;
use Database\Seeders\MarketplaceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * L1 — Stop New Legacy Writes.
 *
 * يثبت إن FreeAppProvisioner::ensure() لم يعد يُستدعى من أي Application Flow
 * طبيعي (تسجيل، دخول، لوحة تحكم، بوابة معرفة). الجدول app_subscriptions نفسه،
 * بياناته القديمة، وقراءات Dashboard الحالية تبقى كما هي — هذا اختبار Write Guard
 * فقط، لا اختبار حذف/Migration. راجع docs/legacy-subscription-l1-spec.md (AD-014).
 */
class LegacyWriteCutoffTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_does_not_write_to_legacy_app_subscriptions(): void
    {
        $before = AppSubscription::count();

        $response = $this->post('/register', [
            'name' => 'مستخدم جديد',
            'email' => 'legacy-guard@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertSame($before, AppSubscription::count());
    }

    public function test_marefa_home_visit_does_not_write_to_legacy_app_subscriptions(): void
    {
        $user = User::factory()->create();
        $before = AppSubscription::count();

        $this->actingAs($user)->get('/marefa')->assertOk();

        $this->assertSame($before, AppSubscription::count());
    }

    public function test_dashboard_visit_does_not_write_to_legacy_app_subscriptions(): void
    {
        $user = User::factory()->create();
        $before = AppSubscription::count();

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $this->assertSame($before, AppSubscription::count());
    }

    /**
     * السيناريو الكامل المطلوب صراحة: مستخدم جديد تمامًا يمر بكل الرحلة
     * الطبيعية، ونتحقق إن الجدول القديم ثابت والجداول الجديدة لم تتأثر إلا
     * عبر فعل Marketplace صريح (وهو غير موجود بهذا السيناريو أصلًا).
     */
    public function test_full_new_user_journey_leaves_legacy_and_new_tables_untouched_without_explicit_marketplace_action(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);

        $legacyBefore = AppSubscription::count();
        $subscriptionsBefore = DB::table('subscriptions')->count();
        $accessBefore = DB::table('access_assignments')->count();

        $this->post('/register', [
            'name' => 'رحلة كاملة',
            'email' => 'full-journey@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'full-journey@example.com')->firstOrFail();

        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->actingAs($user)->get('/marketplace')->assertOk();
        $this->actingAs($user)->get('/my/apps')->assertOk();

        $this->assertSame($legacyBefore, AppSubscription::count());
        $this->assertSame($subscriptionsBefore, DB::table('subscriptions')->count());
        $this->assertSame($accessBefore, DB::table('access_assignments')->count());

        // النتيجة المرئية المقصودة: المستخدم الجديد لا يرى بوابة معرفة مفعّلة
        // تلقائيًا بلوحته — هذا التغيير الموثَّق صراحة بتقرير الإكمال.
        $this->assertFalse($user->hasActiveSubscription('marefa'));
    }
}
