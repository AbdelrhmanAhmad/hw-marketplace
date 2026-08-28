<?php

namespace App\Console\Commands;

use App\Enums\AuditEvent;
use App\Models\AccessAssignment;
use App\Models\AppSubscription;
use App\Models\AuditLog;
use App\Models\MarketplaceItem;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * L2 — Safe Migration. راجع docs/legacy-subscription-l2-safe-migration-specification.md قسم 9.
 * أداة قراءة فقط بالكامل — لا تكتب، لا تُصلح، فقط تُبلِّغ.
 */
class MarketplaceSubscriptionParityCheck extends Command
{
    protected $signature = 'marketplace:subscription-parity-check';

    protected $description = 'يقارن Legacy app_subscriptions مقابل subscriptions/access_assignments الجديدين — قراءة فقط';

    public function handle(): int
    {
        $legacyActive = AppSubscription::where('status', 'active')->get();

        $alreadySynced = 0;
        $explicitlyExcluded = 0;
        $eligibleGap = 0;

        foreach ($legacyActive as $legacy) {
            $item = MarketplaceItem::where('key', $legacy->app_key)->first();

            if (! $item) {
                continue;
            }

            $existing = Subscription::where('subscriber_type', 'user')
                ->where('subscriber_id', $legacy->user_id)
                ->where('marketplace_item_id', $item->id)
                ->first();

            if (! $existing) {
                $eligibleGap++;

                continue;
            }

            if ($existing->status === 'active') {
                $alreadySynced++;
            } else {
                $explicitlyExcluded++;
            }
        }

        $duplicateSubscriptions = Subscription::select('subscriber_type', 'subscriber_id', 'marketplace_item_id')
            ->groupBy('subscriber_type', 'subscriber_id', 'marketplace_item_id')
            ->havingRaw('count(*) > 1')
            ->get()
            ->count();

        $duplicateAccess = AccessAssignment::select('user_id', 'subscription_id')
            ->groupBy('user_id', 'subscription_id')
            ->havingRaw('count(*) > 1')
            ->get()
            ->count();

        $orphanAccess = AccessAssignment::whereNotIn('subscription_id', Subscription::pluck('id'))->count();

        $incorrectlyReactivated = $this->countIncorrectlyReactivated();

        $this->info('=== Subscription/Access Parity Check ===');
        $this->newLine();
        $this->line("Legacy-active users (كل التطبيقات): {$legacyActive->count()}");
        $this->line("  → Already synced (New Active):        {$alreadySynced}");
        $this->line("  → Explicitly excluded (Cancelled/غيره): {$explicitlyExcluded}");
        $this->line("  → Eligible for migration (residual gap): {$eligibleGap}");
        $this->newLine();
        $this->line("Residual migration gap:                  {$eligibleGap}");
        $this->line("Duplicate subscriptions:                 {$duplicateSubscriptions}");
        $this->line("Duplicate access assignments:             {$duplicateAccess}");
        $this->line("Orphan access assignments:                {$orphanAccess}");
        $this->line("Cancelled users incorrectly reactivated:  {$incorrectlyReactivated}");

        $healthy = $eligibleGap === 0
            && $duplicateSubscriptions === 0
            && $duplicateAccess === 0
            && $orphanAccess === 0
            && $incorrectlyReactivated === 0;

        $this->newLine();

        if ($healthy) {
            $this->info('✅ سليم — لا فجوات، لا تكرار، لا انتهاك AD-014.');

            return self::SUCCESS;
        }

        $this->error('❌ فجوات موجودة — راجع الأرقام أعلاه.');

        return self::FAILURE;
    }

    /**
     * الفحص الأهم: أي Subscription له سجل SubscriptionCancelled بالتاريخ،
     * تلاه لاحقًا (id أكبر) سجل SubscriptionCreated/Activated بمصدر L2 —
     * التوقيع الدقيق لخطر AD-014 (إعادة تفعيل بعد إلغاء صريح).
     */
    private function countIncorrectlyReactivated(): int
    {
        return DB::table('audit_logs as cancelled')
            ->join('audit_logs as reactivated', function ($join) {
                $join->on('reactivated.subject_type', '=', 'cancelled.subject_type')
                    ->on('reactivated.subject_id', '=', 'cancelled.subject_id')
                    ->where('reactivated.id', '>', DB::raw('cancelled.id'));
            })
            ->where('cancelled.event', AuditEvent::SubscriptionCancelled->value)
            ->whereIn('reactivated.event', [AuditEvent::SubscriptionCreated->value, AuditEvent::SubscriptionActivated->value])
            ->where('reactivated.metadata->source', 'legacy_migration_l2')
            ->distinct('cancelled.subject_id')
            ->count('cancelled.subject_id');
    }
}
