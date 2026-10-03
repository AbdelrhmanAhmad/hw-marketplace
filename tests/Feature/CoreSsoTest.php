<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CoreSsoProvisioningService;
use App\Support\SafeIntendedPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CoreSsoTest extends TestCase
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
    }

    public function test_safe_intended_path_rejects_open_redirects(): void
    {
        $this->assertSame('/marketplace/foo', SafeIntendedPath::sanitize('/marketplace/foo'));
        $this->assertSame('/marketplace', SafeIntendedPath::sanitize('https://evil.test/x'));
        $this->assertSame('/marketplace', SafeIntendedPath::sanitize('//evil.test'));
        $this->assertSame('/marketplace', SafeIntendedPath::sanitize('/auth/core/redirect'));
    }

    public function test_redirect_stores_state_and_sends_to_core(): void
    {
        $response = $this->get('/auth/core/redirect?intended=/marketplace/marefa');

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('http://localhost:8001/sso/marketplace/authorize?', $location);
        $this->assertNotEmpty(session('core_sso.state'));
        $this->assertSame('/marketplace/marefa', session('core_sso.intended'));
    }

    public function test_callback_jits_new_user_and_logs_in(): void
    {
        Http::fake([
            'http://localhost:8001/api/sso/marketplace/exchange' => Http::response([
                'user' => [
                    'id' => 42,
                    'name' => 'Core Person',
                    'email' => 'core.person@example.com',
                    'email_verified_at' => now()->toIso8601String(),
                ],
            ], 200),
        ]);

        $state = 'state-token';
        $this->withSession([
            'core_sso.state' => $state,
            'core_sso.intended' => '/marketplace',
        ])->get('/auth/core/callback?code=plain-code&state='.$state)
            ->assertRedirect('/marketplace');

        $this->assertAuthenticated();
        $user = User::query()->where('core_user_id', 42)->first();
        $this->assertNotNull($user);
        $this->assertSame('core.person@example.com', $user->email);
        $this->assertFalse($user->is_platform_staff);
    }

    public function test_callback_links_legacy_non_staff_by_email(): void
    {
        $legacy = User::factory()->create([
            'email' => 'legacy@example.com',
            'core_user_id' => null,
            'is_platform_staff' => false,
        ]);

        Http::fake([
            'http://localhost:8001/api/sso/marketplace/exchange' => Http::response([
                'user' => [
                    'id' => 99,
                    'name' => 'Legacy',
                    'email' => 'legacy@example.com',
                    'email_verified_at' => null,
                ],
            ], 200),
        ]);

        $state = 's2';
        $this->withSession([
            'core_sso.state' => $state,
            'core_sso.intended' => '/dashboard',
        ])->get('/auth/core/callback?code=c&state='.$state)
            ->assertRedirect('/dashboard');

        $this->assertSame(99, (int) $legacy->fresh()->core_user_id);
        $this->assertAuthenticatedAs($legacy->fresh());
    }

    public function test_callback_never_auto_links_platform_staff(): void
    {
        User::factory()->create([
            'email' => 'staff@example.com',
            'is_platform_staff' => true,
            'core_user_id' => null,
        ]);

        Http::fake([
            'http://localhost:8001/api/sso/marketplace/exchange' => Http::response([
                'user' => [
                    'id' => 7,
                    'name' => 'Staff',
                    'email' => 'staff@example.com',
                    'email_verified_at' => null,
                ],
            ], 200),
        ]);

        $state = 's3';
        $this->withSession([
            'core_sso.state' => $state,
            'core_sso.intended' => '/marketplace',
        ])->get('/auth/core/callback?code=c&state='.$state)
            ->assertRedirect(route('platform.marketplace'))
            ->assertSessionHasErrors('sso');

        $this->assertGuest();
        $this->assertNull(User::query()->where('core_user_id', 7)->first());
    }

    public function test_provisioning_blocks_conflicting_core_user_id(): void
    {
        User::factory()->create([
            'email' => 'taken@example.com',
            'core_user_id' => 1,
            'is_platform_staff' => false,
        ]);

        $this->expectException(\RuntimeException::class);

        app(CoreSsoProvisioningService::class)->resolveLocalUser([
            'id' => 2,
            'name' => 'Other',
            'email' => 'taken@example.com',
            'email_verified_at' => null,
        ]);
    }

    public function test_customer_login_and_register_redirect_to_core_sso(): void
    {
        $this->get('/login')->assertRedirect();
        $this->assertStringContainsString('/auth/core/redirect', $this->get('/login')->headers->get('Location'));

        $this->get('/register')->assertRedirect();
        $location = $this->get('/register')->headers->get('Location');
        $this->assertStringContainsString('/auth/core/redirect', $location);
    }

    public function test_filament_admin_login_route_remains_local(): void
    {
        $this->get('/admin/login')->assertOk();
    }
}
