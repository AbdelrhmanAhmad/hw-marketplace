<?php

namespace App\Support;

use App\Models\AppSubscription;
use App\Models\User;

class FreeAppProvisioner
{
    /**
     * يضمن اشتراك المستخدم بكل تطبيق متاح ومجاني حاليًا (بوابة معرفة إلخ).
     * آمن للاستدعاء المتكرر (idempotent) عبر firstOrCreate.
     */
    public static function ensure(User $user): void
    {
        foreach (PlatformApps::all() as $app) {
            if (($app['status'] ?? null) !== 'available' || ! ($app['free'] ?? false)) {
                continue;
            }

            AppSubscription::firstOrCreate(
                ['user_id' => $user->id, 'app_key' => $app['key']],
                ['status' => 'active', 'subscribed_at' => now()],
            );
        }
    }
}
