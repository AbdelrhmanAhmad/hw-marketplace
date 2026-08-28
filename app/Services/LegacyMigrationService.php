<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Models\AppSubscription;
use App\Models\AuditLog;
use App\Models\MarketplaceItem;
use App\Models\Subscription;
use App\Models\User;

/**
 * L2 — Safe Migration. راجع docs/legacy-subscription-l2-safe-migration-specification.md.
 *
 * قاعدة الأهلية الوحيدة (قسم ٤): Legacy.status='active' AND لا يوجد أي سجل
 * جديد بأي حالة إطلاقًا لنفس (user, item) — فحص exists() الخام، لا active().
 *
 * لا تستدعي SubscriptionService::subscribeUserToFreeItem() إلا بعد هذا
 * الفحص المستقل — التابع نفسها تحتوي فرعًا يُعيد تفعيل اشتراكات غير فعّالة
 * تلقائيًا (سلوك صحيح لفعل مستخدم مباشر، خطِر لسياق Migration، قسم ٥).
 */
class LegacyMigrationService
{
    public function __construct(private readonly SubscriptionService $subscriptions)
    {
    }

    /**
     * قراءة فقط بالكامل — لا كتابة، تُستخدَم لكل من --dry-run والتقرير النهائي.
     * ستة تصنيفات دقيقة (بطلب المستخدم صراحة، لا تجميع فضفاض):
     *
     * - eligible: Legacy active + لا سجل New بأي حالة إطلاقًا
     * - already_migrated_by_l2: سجل New موجود، أُنشئ سابقًا عبر L2 نفسها (Idempotency، مثبَت بـProvenance لا تخمينًا)
     * - protected_active: سجل New موجود بحالة active، لم يُنشأ عبر L2 (فعل مستخدم مباشر)
     * - protected_cancelled: سجل New موجود بأي حالة غير active (عادة cancelled) — AD-014
     * - ineligible_legacy_inactive: Legacy نفسه غير active، لا New يقابله
     * - unexpected: لا عنصر Marketplace مطابق لـapp_key، أو المستخدم غير موجود
     *
     * @return array{eligible: array, already_migrated_by_l2: array, protected_active: array, protected_cancelled: array, ineligible_legacy_inactive: array, unexpected: array}
     */
    public function classify(): array
    {
        $eligible = [];
        $alreadyMigratedByL2 = [];
        $protectedActive = [];
        $protectedCancelled = [];
        $ineligibleLegacyInactive = [];
        $unexpected = [];

        foreach (AppSubscription::all() as $legacy) {
            $item = MarketplaceItem::where('key', $legacy->app_key)->first();

            if (! $item) {
                $unexpected[] = [
                    'user_id' => $legacy->user_id,
                    'app_key' => $legacy->app_key,
                    'reason' => 'لا عنصر Marketplace مطابق لهذا app_key بالكتالوج',
                ];

                continue;
            }

            $user = User::find($legacy->user_id);

            if (! $user) {
                $unexpected[] = [
                    'user_id' => $legacy->user_id,
                    'app_key' => $legacy->app_key,
                    'reason' => 'المستخدم غير موجود',
                ];

                continue;
            }

            $existing = Subscription::where('subscriber_type', 'user')
                ->where('subscriber_id', $user->id)
                ->where('marketplace_item_id', $item->id)
                ->first();

            if ($existing) {
                $entry = [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'item_key' => $item->key,
                    'subscription_id' => $existing->id,
                    'existing_status' => $existing->status,
                ];

                if ($this->wasCreatedByL2($existing)) {
                    $alreadyMigratedByL2[] = $entry + ['reason' => 'سجل موجود، أُنشئ عبر L2 بتشغيلة سابقة — Idempotent Skip'];
                } elseif ($existing->status === 'active') {
                    $protectedActive[] = $entry + ['reason' => 'سجل Marketplace فعّال موجود بالفعل (فعل مستخدم مباشر) — لا فعل'];
                } else {
                    $protectedCancelled[] = $entry + ['reason' => "سجل Marketplace موجود بحالة [{$existing->status}] — لا فعل بصرف النظر عن حالة Legacy (AD-014)"];
                }

                continue;
            }

            if ($legacy->status !== 'active') {
                $ineligibleLegacyInactive[] = [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'app_key' => $legacy->app_key,
                    'reason' => "Legacy نفسه بحالة [{$legacy->status}]، لا وصول فعّال يُرحَّل",
                ];

                continue;
            }

            $eligible[] = [
                'user_id' => $user->id,
                'email' => $user->email,
                'item_key' => $item->key,
                'legacy_record_id' => $legacy->id,
            ];
        }

        return [
            'eligible' => $eligible,
            'already_migrated_by_l2' => $alreadyMigratedByL2,
            'protected_active' => $protectedActive,
            'protected_cancelled' => $protectedCancelled,
            'ineligible_legacy_inactive' => $ineligibleLegacyInactive,
            'unexpected' => $unexpected,
        ];
    }

    /**
     * إثبات (لا تخمين) إن سجل Subscription موجود أُنشئ عبر L2 بتشغيلة سابقة —
     * يعتمد على Provenance الفعلي بـaudit_logs، لا على أي قاعدة تخمينية.
     */
    private function wasCreatedByL2(Subscription $subscription): bool
    {
        return AuditLog::where('subject_type', Subscription::class)
            ->where('subject_id', $subscription->id)
            ->where('event', AuditEvent::SubscriptionCreated->value)
            ->where('metadata->source', 'legacy_migration_l2')
            ->exists();
    }

    /**
     * تنفيذ فعلي — يُرحِّل فقط ما صنَّفه classify() كـ"eligible". يُعيد نفس
     * البنية بالإضافة لـ'migrated'/'failed'.
     *
     * كل مستخدم مُعزول تمامًا عن البقية (try/catch فردي + Transaction خاصة به
     * داخل subscribeUserToFreeItem) — فشل مستخدم واحد لا يمنع أو يُلغي معالجة
     * أي مستخدم آخر بنفس التشغيلة (قسم 13، اختبار Partial Failure).
     *
     * @return array{eligible: array, migrated: array, failed: array, skipped_existing: array, skipped_legacy_inactive: array}
     */
    public function execute(string $migrationRunId): array
    {
        $classification = $this->classify();
        $migrated = [];
        $failed = [];

        foreach ($classification['eligible'] as $entry) {
            try {
                $user = User::findOrFail($entry['user_id']);
                $item = MarketplaceItem::where('key', $entry['item_key'])->firstOrFail();

                $subscription = $this->subscriptions->subscribeUserToFreeItem($user, $item, [
                    'source' => 'legacy_migration_l2',
                    'migration_run_id' => $migrationRunId,
                    'legacy_record_id' => $entry['legacy_record_id'],
                    'legacy_app_key' => $item->key,
                    'reason' => 'legacy_active_no_new_record',
                ]);

                $migrated[] = [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'item_key' => $item->key,
                    'subscription_id' => $subscription->id,
                ];
            } catch (\Throwable $e) {
                $failed[] = [
                    'user_id' => $entry['user_id'],
                    'item_key' => $entry['item_key'] ?? null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return array_merge($classification, ['migrated' => $migrated, 'failed' => $failed]);
    }

    /**
     * قسم ١١ — Migration Provenance. مسموح فقط لو لا يوجد أي AuditLog لاحق
     * (بترتيب id التصاعدي) على نفس الموضوع بعد **آخر** سجل ينتمي لعملية
     * إنشاء L2 نفسها — لا أول سجل فقط. subscribeUserToFreeItem() يكتب
     * حدثين على موضوع Subscription نفسه لكل إنشاء (SubscriptionCreated ثم
     * SubscriptionActivated، كلاهما بنفس metadata.source) — مقارنة "أي نشاط
     * لاحق" بأول حدث فقط كانت تعتبر الحدث الثاني (من نفس عملية L2) نشاطًا
     * لاحقًا خطأً، فترفض Rollback دائمًا حتى بلا أي تدخل مستخدم إطلاقًا.
     */
    public function rollback(Subscription $subscription): RollbackOutcome
    {
        $migrationEvents = AuditLog::where('subject_type', Subscription::class)
            ->where('subject_id', $subscription->id)
            ->where('metadata->source', 'legacy_migration_l2')
            ->get();

        if ($migrationEvents->isEmpty()) {
            return RollbackOutcome::refused('هذا السجل لم يُنشأ بواسطة L2 أصلًا — Rollback لا يخصّه.');
        }

        $lastMigrationEventId = $migrationEvents->max('id');

        $laterActivity = AuditLog::where('subject_type', Subscription::class)
            ->where('subject_id', $subscription->id)
            ->where('id', '>', $lastMigrationEventId)
            ->exists();

        if ($laterActivity) {
            return RollbackOutcome::refused('يوجد نشاط لاحق على هذا الاشتراك بعد L2 — قد يكون قرار مستخدم، لا يُلمَس.');
        }

        $this->subscriptions->cancel($subscription, [
            'source' => 'legacy_migration_l2_rollback',
            'rolled_back_migration_event_id' => $lastMigrationEventId,
        ]);

        return RollbackOutcome::rolledBack();
    }
}
