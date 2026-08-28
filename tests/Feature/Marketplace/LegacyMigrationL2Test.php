<?php

namespace Tests\Feature\Marketplace;

use App\Console\Commands\MarketplaceMigrateLegacySubscriptions;
use App\Enums\AuditEvent;
use App\Models\AccessAssignment;
use App\Models\AppSubscription;
use App\Models\AuditLog;
use App\Models\MarketplaceItem;
use App\Models\Subscription;
use App\Models\User;
use App\Services\LegacyMigrationService;
use App\Services\SubscriptionService;
use Database\Seeders\MarketplaceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * L2 — Safe Migration. راجع docs/legacy-subscription-l2-safe-migration-specification.md
 * (قسم 13، Test Matrix) — الأرقام هنا تطابق قائمة الـ14 سيناريو الإلزامية
 * اللي حددها المستخدم صراحة وقت التصريح بالتنفيذ.
 */
class LegacyMigrationL2Test extends TestCase
{
    use RefreshDatabase;

    private function marefa(): MarketplaceItem
    {
        $this->seed(MarketplaceCatalogSeeder::class);

        return MarketplaceItem::where('key', 'marefa')->firstOrFail();
    }

    private function legacyActive(User $user, string $appKey = 'marefa'): AppSubscription
    {
        return AppSubscription::create([
            'user_id' => $user->id,
            'app_key' => $appKey,
            'status' => 'active',
            'subscribed_at' => now(),
        ]);
    }

    /** #1 — Legacy active + no new record → Eligible. */
    public function test_1_legacy_active_no_new_record_is_eligible(): void
    {
        $this->marefa();
        $user = User::factory()->create();
        $legacy = $this->legacyActive($user);

        $result = app(LegacyMigrationService::class)->classify();

        $this->assertCount(1, $result['eligible']);
        $this->assertSame($user->id, $result['eligible'][0]['user_id']);
        $this->assertSame($legacy->id, $result['eligible'][0]['legacy_record_id']);
        $this->assertEmpty($result['protected_active']);
        $this->assertEmpty($result['protected_cancelled']);
        $this->assertEmpty($result['already_migrated_by_l2']);
    }

    /** #2 — Legacy active + active new record → Protected. */
    public function test_2_legacy_active_with_active_new_record_is_protected(): void
    {
        $item = $this->marefa();
        $user = User::factory()->create();
        $this->legacyActive($user);
        app(SubscriptionService::class)->subscribeUserToFreeItem($user, $item);

        $result = app(LegacyMigrationService::class)->classify();

        $this->assertEmpty($result['eligible']);
        $this->assertCount(1, $result['protected_active']);
        $this->assertSame('active', $result['protected_active'][0]['existing_status']);
        $this->assertEmpty($result['already_migrated_by_l2'], 'أُنشئ مباشرة عبر SubscriptionService لا L2 — لا يُصنَّف already_migrated_by_l2');
    }

    /** #3 — Legacy active + cancelled new record → Protected (AD-014، لا يُلمَس أبدًا). */
    public function test_3_legacy_active_with_cancelled_new_record_is_protected(): void
    {
        $item = $this->marefa();
        $user = User::factory()->create();
        $this->legacyActive($user);
        $service = app(SubscriptionService::class);
        $subscription = $service->subscribeUserToFreeItem($user, $item);
        $service->cancel($subscription->fresh());

        $result = app(LegacyMigrationService::class)->classify();

        $this->assertEmpty($result['eligible']);
        $this->assertCount(1, $result['protected_cancelled']);
        $this->assertSame('cancelled', $result['protected_cancelled'][0]['existing_status']);
    }

    /** #4 — Legacy inactive + no new record → Ineligible. */
    public function test_4_legacy_inactive_no_new_record_is_ineligible(): void
    {
        $this->marefa();
        $user = User::factory()->create();
        AppSubscription::create([
            'user_id' => $user->id,
            'app_key' => 'marefa',
            'status' => 'cancelled',
            'subscribed_at' => now(),
        ]);

        $result = app(LegacyMigrationService::class)->classify();

        $this->assertEmpty($result['eligible']);
        $this->assertCount(1, $result['ineligible_legacy_inactive']);
        $this->assertSame($user->id, $result['ineligible_legacy_inactive'][0]['user_id']);
    }

    /** #5 و#6 — Migration تُنشئ Subscription وAccessAssignment صحيحين. */
    public function test_5_6_migration_creates_correct_subscription_and_access_assignment(): void
    {
        $this->marefa();
        $user = User::factory()->create();
        $this->legacyActive($user);

        app(LegacyMigrationService::class)->execute('test-run-56');

        $subscription = Subscription::where('subscriber_type', 'user')->where('subscriber_id', $user->id)->firstOrFail();
        $this->assertSame('active', $subscription->status);
        $this->assertSame('user', $subscription->subscriber_type);

        $access = AccessAssignment::where('user_id', $user->id)->where('subscription_id', $subscription->id)->firstOrFail();
        $this->assertSame('active', $access->status);
    }

    /** #7 — Migration تُنشئ AuditLog صحيح (3 أحداث + Provenance كامل). */
    public function test_7_migration_creates_correct_audit_log_with_full_provenance(): void
    {
        $this->marefa();
        $user = User::factory()->create();
        $legacy = $this->legacyActive($user);

        app(LegacyMigrationService::class)->execute('test-run-7');

        $subscription = Subscription::where('subscriber_id', $user->id)->firstOrFail();

        $created = AuditLog::where('subject_type', Subscription::class)
            ->where('subject_id', $subscription->id)
            ->where('event', AuditEvent::SubscriptionCreated->value)
            ->firstOrFail();

        $this->assertSame('legacy_migration_l2', $created->metadata['source']);
        $this->assertSame('test-run-7', $created->metadata['migration_run_id']);
        $this->assertSame($legacy->id, $created->metadata['legacy_record_id']);
        $this->assertSame('marefa', $created->metadata['legacy_app_key']);

        $this->assertTrue(
            AuditLog::where('subject_type', Subscription::class)->where('subject_id', $subscription->id)
                ->where('event', AuditEvent::SubscriptionActivated->value)->exists()
        );
        $this->assertTrue(
            AuditLog::where('subject_type', AccessAssignment::class)
                ->where('event', AuditEvent::AccessGranted->value)->exists()
        );
    }

    /** #8 — تشغيل مزدوج Idempotent: التشغيلة الثانية صفر فعل جديد، صفر Duplicate. */
    public function test_8_duplicate_execution_is_idempotent(): void
    {
        $this->marefa();
        $user = User::factory()->create();
        $this->legacyActive($user);
        $service = app(LegacyMigrationService::class);

        $first = $service->execute('test-run-8a');
        $this->assertCount(1, $first['migrated']);

        $second = $service->execute('test-run-8b');
        $this->assertEmpty($second['migrated']);
        $this->assertCount(1, $second['already_migrated_by_l2']);

        $this->assertSame(1, Subscription::where('subscriber_id', $user->id)->count());
        $this->assertSame(1, AccessAssignment::where('user_id', $user->id)->count());
    }

    /** #9 — مستخدم ملغى لا يُعاد تفعيله أبدًا، عبر تشغيلات متعددة. */
    public function test_9_cancelled_user_is_never_reactivated_across_multiple_runs(): void
    {
        $item = $this->marefa();
        $user = User::factory()->create();
        $this->legacyActive($user);
        $service = app(SubscriptionService::class);
        $subscription = $service->subscribeUserToFreeItem($user, $item);
        $service->cancel($subscription->fresh());

        app(LegacyMigrationService::class)->execute('test-run-9a');
        app(LegacyMigrationService::class)->execute('test-run-9b');
        app(LegacyMigrationService::class)->execute('test-run-9c');

        $this->assertSame('cancelled', $subscription->fresh()->status);
        $this->assertSame(1, Subscription::where('subscriber_id', $user->id)->count());
    }

    /** #10 — Rollback قبل أي نشاط مستخدم لاحق → ينجح. */
    public function test_10_rollback_before_user_activity_succeeds(): void
    {
        $this->marefa();
        $user = User::factory()->create();
        $this->legacyActive($user);

        app(LegacyMigrationService::class)->execute('test-run-10');
        $subscription = Subscription::where('subscriber_id', $user->id)->firstOrFail();

        $outcome = app(LegacyMigrationService::class)->rollback($subscription->fresh());

        $this->assertTrue($outcome->succeeded);
        $this->assertSame('cancelled', $subscription->fresh()->status);
    }

    /** #11 — Rollback بعد إلغاء المستخدم بنفسه → مرفوض. */
    public function test_11_rollback_after_user_cancellation_is_refused(): void
    {
        $this->marefa();
        $user = User::factory()->create();
        $this->legacyActive($user);

        app(LegacyMigrationService::class)->execute('test-run-11');
        $subscription = Subscription::where('subscriber_id', $user->id)->firstOrFail();

        $this->actingAs($user)->post(route('platform.marketplace.cancel', 'marefa'))->assertRedirect();

        $outcome = app(LegacyMigrationService::class)->rollback($subscription->fresh());

        $this->assertFalse($outcome->succeeded);
        $this->assertSame('cancelled', $subscription->fresh()->status);
    }

    /**
     * #12 — Rollback بعد أي حدث Audit لاحق (لا الإلغاء تحديدًا) → مرفوض.
     * يثبت إن الفحص عام (أي نشاط لاحق على الموضوع)، لا مُخصَّص لحالة الإلغاء فقط.
     */
    public function test_12_rollback_after_any_later_audit_event_is_refused(): void
    {
        $this->marefa();
        $user = User::factory()->create();
        $this->legacyActive($user);

        app(LegacyMigrationService::class)->execute('test-run-12');
        $subscription = Subscription::where('subscriber_id', $user->id)->firstOrFail();

        // حدث Audit لاحق اصطناعي، غير مرتبط بإلغاء المستخدم إطلاقًا — يثبت
        // عمومية الفحص لا خصوصيته لسيناريو الإلغاء وحده.
        AuditLog::create([
            'organization_id' => null,
            'actor_user_id' => $user->id,
            'event' => AuditEvent::AccessRevoked->value,
            'subject_type' => Subscription::class,
            'subject_id' => $subscription->id,
            'metadata' => ['source' => 'unrelated_later_event_for_test'],
        ]);

        $outcome = app(LegacyMigrationService::class)->rollback($subscription->fresh());

        $this->assertFalse($outcome->succeeded);
    }

    /** #13 — الأداة القديمة لا يمكن تشغيلها إطلاقًا (Step 0 مؤكَّد). */
    public function test_13_old_migration_tool_cannot_run(): void
    {
        $this->assertFalse(
            class_exists('App\Console\Commands\MarketplaceBackfillFreeAccess'),
            'MarketplaceBackfillFreeAccess يجب تكون محذوفة كليًا — لا تعايش بين الأداتين'
        );
        $this->assertFileDoesNotExist(app_path('Console/Commands/MarketplaceBackfillFreeAccess.php'));

        $this->artisan('list')->run();
        $this->assertTrue(class_exists(MarketplaceMigrateLegacySubscriptions::class), 'الأداة الجديدة هي المسار الوحيد المتاح');
    }

    /** #14 — AuditLog يبقى Append-only عبر كل عمليات L2 (Migrate + Rollback معًا). */
    public function test_14_audit_log_remains_append_only_through_l2_operations(): void
    {
        $this->marefa();
        $user = User::factory()->create();
        $this->legacyActive($user);

        app(LegacyMigrationService::class)->execute('test-run-14');
        $subscription = Subscription::where('subscriber_id', $user->id)->firstOrFail();
        app(LegacyMigrationService::class)->rollback($subscription->fresh());

        $logs = AuditLog::where('subject_type', Subscription::class)->where('subject_id', $subscription->id)->get();
        $this->assertGreaterThanOrEqual(3, $logs->count(), 'Created+Activated من الترحيل + Cancelled من الـRollback على الأقل');

        $first = $logs->first();

        $this->expectException(LogicException::class);
        try {
            $first->delete();
        } finally {
            $this->assertSame($logs->count(), AuditLog::where('subject_type', Subscription::class)->where('subject_id', $subscription->id)->count());
        }
    }

    // --- اختبارات إضافية من الجولة السابقة، لا تزال جزءًا من التغطية الإلزامية ---

    public function test_no_legacy_record_at_all_is_not_touched(): void
    {
        $this->marefa();
        User::factory()->create();

        $result = app(LegacyMigrationService::class)->execute('test-run-none');

        $this->assertEmpty($result['eligible']);
        $this->assertEmpty($result['migrated']);
    }

    public function test_two_independent_legacy_only_users_are_migrated_independently(): void
    {
        $this->marefa();
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $this->legacyActive($userA);
        $this->legacyActive($userB);

        $result = app(LegacyMigrationService::class)->execute('test-run-two');

        $this->assertCount(2, $result['migrated']);
        $this->assertSame(1, Subscription::where('subscriber_id', $userA->id)->count());
        $this->assertSame(1, Subscription::where('subscriber_id', $userB->id)->count());
    }

    public function test_partial_failure_does_not_block_other_users_in_same_run(): void
    {
        $this->marefa();
        $bankruptcyTech = MarketplaceItem::where('key', 'bankruptcy-tech')->firstOrFail();
        $bankruptcyTech->update(['pricing_model' => 'free', 'billing_model' => 'organization_only']);

        $userOk = User::factory()->create();
        $userBroken = User::factory()->create();
        $this->legacyActive($userOk, 'marefa');
        $this->legacyActive($userBroken, 'bankruptcy-tech');

        $result = app(LegacyMigrationService::class)->execute('test-run-partial');

        $this->assertCount(1, $result['migrated']);
        $this->assertCount(1, $result['failed']);
        $this->assertSame(1, Subscription::where('subscriber_id', $userOk->id)->count());
        $this->assertSame(0, Subscription::where('subscriber_id', $userBroken->id)->count());
    }

    public function test_command_without_force_flag_never_writes(): void
    {
        $this->marefa();
        $user = User::factory()->create();
        $this->legacyActive($user);

        $subBefore = Subscription::count();
        $auditBefore = AuditLog::count();

        $this->artisan('marketplace:migrate-legacy-subscriptions')->assertSuccessful();

        $this->assertSame($subBefore, Subscription::count());
        $this->assertSame($auditBefore, AuditLog::count());
    }

    public function test_command_with_force_flag_writes(): void
    {
        $this->marefa();
        $user = User::factory()->create();
        $this->legacyActive($user);

        $this->artisan('marketplace:migrate-legacy-subscriptions', ['--force' => true])->assertSuccessful();

        $this->assertSame(1, Subscription::where('subscriber_id', $user->id)->count());
    }

    public function test_dry_run_leaves_all_tables_untouched(): void
    {
        $this->marefa();
        $user = User::factory()->create();
        $this->legacyActive($user);

        $subBefore = Subscription::count();
        $accessBefore = AccessAssignment::count();
        $auditBefore = AuditLog::count();

        app(LegacyMigrationService::class)->classify();

        $this->assertSame($subBefore, Subscription::count());
        $this->assertSame($accessBefore, AccessAssignment::count());
        $this->assertSame($auditBefore, AuditLog::count());
    }

    public function test_rollback_is_refused_for_a_subscription_not_created_by_l2(): void
    {
        $item = $this->marefa();
        $user = User::factory()->create();

        $subscription = app(SubscriptionService::class)->subscribeUserToFreeItem($user, $item);

        $outcome = app(LegacyMigrationService::class)->rollback($subscription->fresh());

        $this->assertFalse($outcome->succeeded);
        $this->assertSame('active', $subscription->fresh()->status);
    }
}
