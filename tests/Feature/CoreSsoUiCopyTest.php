<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreSsoUiCopyTest extends TestCase
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

    public function test_platform_shell_shows_core_sso_login_copy(): void
    {
        $this->get(route('platform.home'))
            ->assertOk()
            ->assertSee('تسجيل الدخول بحساب حكم ورقم', false)
            ->assertSee('إنشاء حساب حكم ورقم', false)
            ->assertSee('جارٍ تسجيل الدخول عبر حكم ورقم...', false);
    }

    public function test_callback_flashes_sso_success_toast_flag(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'http://localhost:8001/api/sso/marketplace/exchange' => \Illuminate\Support\Facades\Http::response([
                'user' => [
                    'id' => 11,
                    'name' => 'Toast User',
                    'email' => 'toast@example.com',
                    'email_verified_at' => now()->toIso8601String(),
                ],
            ], 200),
        ]);

        $state = 'toast-state';
        $this->withSession([
            'core_sso.state' => $state,
            'core_sso.intended' => '/marketplace',
        ])->get('/auth/core/callback?code=plain&state='.$state)
            ->assertRedirect('/marketplace')
            ->assertSessionHas('sso_login_success', true);
    }
}
