<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Platform Authorization Foundation — الآلية الوحيدة لمنح/سحب Platform
 * Staff. عمدًا CLI فقط، لا Route، لا Form، لا مسار HTTP يصل له — منع أي
 * دائرية (تحتاج تكون Staff أصلًا لتصل لواجهة تمنح Staff). راجع
 * docs/platform-authorization-foundation-specification.md §1.3.
 *
 * Idempotent: تشغيله على مستخدم بنفس الحالة المستهدَفة أصلًا لا يُغيّر شيئًا
 * ولا يكتب سجل "granted/revoked" مضلِّل — يكتب سجل "no_change" منفصل بدلًا
 * من ذلك، ليبقى سجل platform_security دقيقًا لمن يراجعه لاحقًا.
 */
class PlatformGrantStaff extends Command
{
    protected $signature = 'platform:grant-staff
        {email : البريد الإلكتروني للمستخدم المطلوب منحه/سحب صلاحية Platform Staff}
        {--revoke : سحب الصلاحية بدل منحها}
        {--force : تنفيذ بلا تأكيد تفاعلي}';

    protected $description = 'CLI-only bootstrap — يمنح/يسحب صلاحية Platform Staff (لا Route ولا UI يصل لهذا الفعل)';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $revoke = (bool) $this->option('revoke');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("لا يوجد مستخدم بالبريد الإلكتروني: {$email}");

            return self::FAILURE;
        }

        $targetState = ! $revoke;
        $currentState = $user->isPlatformStaff();
        $action = $revoke ? 'سحب' : 'منح';

        if ($currentState === $targetState) {
            $this->info("لا تغيير: المستخدم [{$user->email}] بالفعل ".($targetState ? 'Platform Staff' : 'ليس Platform Staff').'.');

            Log::channel('platform_security')->info('platform_staff_bootstrap_no_change', [
                'target_email' => $user->email,
                'target_user_id' => $user->id,
                'requested_action' => $revoke ? 'revoke' : 'grant',
                'current_state' => $currentState,
                'executed_via' => 'cli',
                'os_user' => get_current_user(),
            ]);

            return self::SUCCESS;
        }

        $confirmed = $this->option('force') || $this->confirm(
            "هل تريد فعلًا {$action} صلاحية Platform Staff للمستخدم [{$user->email}]؟"
        );

        if (! $confirmed) {
            $this->warn('تم الإلغاء — لا تغيير.');

            return self::FAILURE;
        }

        // forceFill عمدًا لا update() — is_platform_staff ليس Fillable قصدًا
        // (منع أي مسار Mass Assignment مستقبلي، ولو عبر Form/API لم يُبنَ بعد).
        $user->forceFill(['is_platform_staff' => $targetState])->save();

        Log::channel('platform_security')->info('platform_staff_bootstrap_changed', [
            'target_email' => $user->email,
            'target_user_id' => $user->id,
            'action' => $revoke ? 'revoke' : 'grant',
            'previous_state' => $currentState,
            'new_state' => $targetState,
            'executed_via' => 'cli',
            'os_user' => get_current_user(),
        ]);

        $this->info("تم: المستخدم [{$user->email}] الآن ".($targetState ? 'Platform Staff' : 'ليس Platform Staff').'.');

        return self::SUCCESS;
    }
}
