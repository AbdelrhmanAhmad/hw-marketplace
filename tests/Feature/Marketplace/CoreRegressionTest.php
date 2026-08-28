<?php

namespace Tests\Feature\Marketplace;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * يتأكد إن Phase 1a (كتالوج فقط) لم يمسّ أي جزء من Core Platform أو بوابة
 * معرفة — لا Subscription/Access/Dashboard/Auth بهذي المرحلة إطلاقًا.
 */
class CoreRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_still_loads(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_register_page_still_loads(): void
    {
        $this->get('/register')->assertOk();
    }

    public function test_marefa_home_still_loads(): void
    {
        $this->get('/marefa')->assertOk();
    }

    public function test_laws_index_still_loads(): void
    {
        $this->get('/laws')->assertOk();
    }

    public function test_updates_index_still_loads(): void
    {
        $this->get('/updates')->assertOk();
    }

    public function test_gratuity_calculator_still_loads(): void
    {
        $this->get('/calculators/gratuity')->assertOk();
    }

    public function test_authenticated_dashboard_still_loads(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
    }

    /**
     * L1 (Legacy Write Cutoff) intentionally retired this behavior: Dashboard no longer
     * calls FreeAppProvisioner::ensure(), so a new user is not silently auto-granted a
     * legacy AppSubscription on first visit. See docs/phase-l1-legacy-write-cutoff-completion-report.md.
     */
    public function test_dashboard_no_longer_auto_grants_legacy_free_app_subscription(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard');

        $this->assertFalse($user->hasActiveSubscription('marefa'));
    }

    public function test_authenticated_bookmarks_page_still_loads(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/bookmarks')->assertOk();
    }

    public function test_platform_home_still_loads(): void
    {
        $this->get('/')->assertOk();
    }
}
