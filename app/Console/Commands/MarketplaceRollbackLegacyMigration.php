<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\LegacyMigrationService;
use Illuminate\Console\Command;

/**
 * L2 — Rollback عملياتي فردي. راجع docs/legacy-subscription-l2-safe-migration-specification.md قسم 11.
 * إلغاء (لا حذف)، ومرفوض بنيويًا لو وُجد نشاط لاحق على السجل بعد إنشاء L2 له.
 */
class MarketplaceRollbackLegacyMigration extends Command
{
    protected $signature = 'marketplace:rollback-legacy-migration {subscription_id : معرّف Subscription المطلوب التراجع عنه}';

    protected $description = 'يتراجع عن Subscription أنشأته L2 تحديدًا — يُرفَض لو وُجد نشاط لاحق (User Intent محمي)';

    public function handle(LegacyMigrationService $migration): int
    {
        $subscription = Subscription::find((int) $this->argument('subscription_id'));

        if (! $subscription) {
            $this->error('لا يوجد Subscription بهذا المعرّف.');

            return self::FAILURE;
        }

        $outcome = $migration->rollback($subscription);

        if (! $outcome->succeeded) {
            $this->error("Rollback مرفوض: {$outcome->reason}");

            return self::FAILURE;
        }

        $this->info("Rollback تم: {$outcome->reason}");

        return self::SUCCESS;
    }
}
