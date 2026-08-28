<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Platform Authorization Foundation — platform:grant-staff، الآلية الوحيدة
 * (CLI فقط، لا Route ولا UI) لمنح/سحب Platform Staff. راجع
 * docs/platform-authorization-foundation-specification.md §1.3.
 */
class PlatformGrantStaffCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_fails_when_user_does_not_exist(): void
    {
        $this->artisan('platform:grant-staff', ['email' => 'no-such-user@example.com', '--force' => true])
            ->assertExitCode(1);
    }

    public function test_grants_staff_to_existing_user(): void
    {
        $user = User::factory()->create(['is_platform_staff' => false]);

        Log::shouldReceive('channel')->with('platform_security')->once()->andReturnSelf();
        Log::shouldReceive('info')->once()->with('platform_staff_bootstrap_changed', \Mockery::on(function (array $context) use ($user) {
            return $context['target_email'] === $user->email
                && $context['action'] === 'grant'
                && $context['previous_state'] === false
                && $context['new_state'] === true;
        }));

        $this->artisan('platform:grant-staff', ['email' => $user->email, '--force' => true])
            ->assertExitCode(0);

        $this->assertTrue($user->fresh()->isPlatformStaff());
    }

    public function test_revokes_staff_from_existing_user(): void
    {
        $user = User::factory()->create(['is_platform_staff' => true]);

        $this->artisan('platform:grant-staff', ['email' => $user->email, '--revoke' => true, '--force' => true])
            ->assertExitCode(0);

        $this->assertFalse($user->fresh()->isPlatformStaff());
    }

    /**
     * Idempotent — تشغيله على مستخدم بنفس الحالة المستهدَفة لا يُغيّر شيئًا
     * ولا يكتب سجل "granted" مضلِّل (سجل "no_change" منفصل بدلًا من ذلك).
     */
    public function test_granting_staff_to_already_staff_user_is_idempotent_no_op(): void
    {
        $user = User::factory()->create(['is_platform_staff' => true]);

        Log::shouldReceive('channel')->with('platform_security')->once()->andReturnSelf();
        Log::shouldReceive('info')->once()->with('platform_staff_bootstrap_no_change', \Mockery::on(function (array $context) use ($user) {
            return $context['target_email'] === $user->email
                && $context['requested_action'] === 'grant'
                && $context['current_state'] === true;
        }));

        $this->artisan('platform:grant-staff', ['email' => $user->email, '--force' => true])
            ->assertExitCode(0);

        $this->assertTrue($user->fresh()->isPlatformStaff());
    }

    public function test_revoking_staff_from_non_staff_user_is_idempotent_no_op(): void
    {
        $user = User::factory()->create(['is_platform_staff' => false]);

        $this->artisan('platform:grant-staff', ['email' => $user->email, '--revoke' => true, '--force' => true])
            ->assertExitCode(0);

        $this->assertFalse($user->fresh()->isPlatformStaff());
    }

    public function test_running_grant_twice_does_not_create_duplicate_users_or_change_id(): void
    {
        $user = User::factory()->create(['is_platform_staff' => false]);
        $countBefore = User::count();

        $this->artisan('platform:grant-staff', ['email' => $user->email, '--force' => true])->assertExitCode(0);
        $this->artisan('platform:grant-staff', ['email' => $user->email, '--force' => true])->assertExitCode(0);

        $this->assertSame($countBefore, User::count());
        $this->assertSame($user->id, $user->fresh()->id);
        $this->assertTrue($user->fresh()->isPlatformStaff());
    }

    public function test_declining_confirmation_makes_no_change(): void
    {
        $user = User::factory()->create(['is_platform_staff' => false]);

        $this->artisan('platform:grant-staff', ['email' => $user->email])
            ->expectsConfirmation("هل تريد فعلًا منح صلاحية Platform Staff للمستخدم [{$user->email}]؟", 'no')
            ->assertExitCode(1);

        $this->assertFalse($user->fresh()->isPlatformStaff());
    }
}
