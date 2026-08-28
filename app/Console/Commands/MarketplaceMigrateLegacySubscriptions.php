<?php

namespace App\Console\Commands;

use App\Services\LegacyMigrationService;
use Illuminate\Console\Command;

/**
 * L2 — Safe Migration. راجع docs/legacy-subscription-l2-safe-migration-specification.md.
 *
 * افتراضي دائمًا: Dry Run (قراءة فقط، صفر كتابة). تنفيذ فعلي يتطلب --force
 * صراحة بكل تشغيلة (قسم 12 — Approval Gate، لا إذن دائم عام).
 */
class MarketplaceMigrateLegacySubscriptions extends Command
{
    protected $signature = 'marketplace:migrate-legacy-subscriptions {--force : تنفيذ فعلي — بدونه الأمر يعمل بوضع Dry Run دائمًا}';

    protected $description = 'يرحّل مستخدمين لهم Legacy Active بلا أي سجل Marketplace بأي حالة إلى النظام الجديد (L2)';

    public function handle(LegacyMigrationService $migration): int
    {
        $force = (bool) $this->option('force');

        if (! $force) {
            $result = $migration->classify();
            $this->reportDryRun($result);

            return self::SUCCESS;
        }

        $migrationRunId = 'l2-'.now()->format('Y-m-d-His').'-'.substr(bin2hex(random_bytes(3)), 0, 6);

        $this->warn("تنفيذ فعلي — Migration Run ID: {$migrationRunId}");

        $result = $migration->execute($migrationRunId);
        $this->reportExecuted($result, $migrationRunId);

        return self::SUCCESS;
    }

    private function reportDryRun(array $result): void
    {
        $this->info('=== Dry Run — لا كتابة، قراءة فقط ===');
        $this->newLine();

        $totalLegacy = count($result['eligible']) + count($result['already_migrated_by_l2'])
            + count($result['protected_active']) + count($result['protected_cancelled'])
            + count($result['ineligible_legacy_inactive']) + count($result['unexpected']);
        $this->line("Legacy records مفحوصة: {$totalLegacy}");
        $this->newLine();

        $this->line('✅ Eligible (سيُرحَّل عند --force):');
        if (empty($result['eligible'])) {
            $this->line('  [لا أحد]');
        }
        foreach ($result['eligible'] as $entry) {
            $this->line("  user {$entry['user_id']} ({$entry['email']})  item={$entry['item_key']}  legacy_record_id={$entry['legacy_record_id']}");
        }

        $this->newLine();
        $this->line('🔁 Already migrated by L2 (تشغيلة سابقة، Idempotent Skip):');
        foreach ($result['already_migrated_by_l2'] as $entry) {
            $this->line("  user {$entry['user_id']} ({$entry['email']})  item={$entry['item_key']}  subscription_id={$entry['subscription_id']}");
        }

        $this->newLine();
        $this->line('🛡 Protected — سجل Marketplace فعّال موجود بالفعل (فعل مستخدم مباشر):');
        foreach ($result['protected_active'] as $entry) {
            $this->line("  user {$entry['user_id']} ({$entry['email']})  item={$entry['item_key']}  subscription_id={$entry['subscription_id']}");
        }

        $this->newLine();
        $this->line('🚫 Protected — سجل Marketplace ملغى (AD-014، لن يُعاد تفعيله أبدًا):');
        foreach ($result['protected_cancelled'] as $entry) {
            $this->line("  user {$entry['user_id']} ({$entry['email']})  item={$entry['item_key']}  existing_status={$entry['existing_status']}");
        }

        $this->newLine();
        $this->line('⚪ Ineligible — Legacy نفسه غير فعّال:');
        foreach ($result['ineligible_legacy_inactive'] as $entry) {
            $this->line("  user {$entry['user_id']}  — {$entry['reason']}");
        }

        $this->newLine();
        $this->line('⚠️ Unexpected — حالات تحتاج مراجعة يدوية:');
        if (empty($result['unexpected'])) {
            $this->line('  [لا شيء]');
        }
        foreach ($result['unexpected'] as $entry) {
            $this->line("  user {$entry['user_id']}  — {$entry['reason']}");
        }

        $this->newLine();
        $this->info('عدد المؤهَّلين للترحيل: '.count($result['eligible']));
    }

    private function reportExecuted(array $result, string $migrationRunId): void
    {
        $this->newLine();
        $this->info('=== نتيجة التنفيذ الفعلي ===');
        $this->line("Migration Run ID: {$migrationRunId}");
        $this->line('مؤهَّلون: '.count($result['eligible']));
        $this->line('رُحِّلوا فعليًا: '.count($result['migrated']));
        foreach ($result['migrated'] as $entry) {
            $this->line("  ✅ user {$entry['user_id']} ({$entry['email']})  item={$entry['item_key']}  subscription_id={$entry['subscription_id']}");
        }
        $this->line('Already migrated by L2: '.count($result['already_migrated_by_l2']));
        $this->line('Protected (فعّال): '.count($result['protected_active']));
        $this->line('Protected (ملغى — AD-014): '.count($result['protected_cancelled']));
        $this->line('Ineligible (Legacy غير فعّال): '.count($result['ineligible_legacy_inactive']));
        $this->line('Unexpected: '.count($result['unexpected']));

        if (! empty($result['failed'])) {
            $this->newLine();
            $this->error('فشل (لم يُرحَّلوا — لا يمنع باقي المستخدمين):');
            foreach ($result['failed'] as $entry) {
                $this->line("  ❌ user {$entry['user_id']}  item={$entry['item_key']}  — {$entry['error']}");
            }
        }
    }
}
