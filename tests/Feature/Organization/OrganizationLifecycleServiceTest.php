<?php

namespace Tests\Feature\Organization;

use App\Enums\AuditEvent;
use App\Enums\MembershipRole;
use App\Filament\Resources\OrganizationResource\Pages\EditOrganization;
use App\Models\AuditLog;
use App\Models\MarketplaceItem;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionSeat;
use App\Models\User;
use App\Services\EntitlementResolver;
use App\Services\OrganizationLifecycleService;
use App\Services\OrganizationSubscriptionService;
use App\Services\SeatService;
use App\Support\ActiveOrganizationContext;
use Database\Seeders\MarketplaceCatalogSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase OL — Organization Lifecycle. راجع docs/phase-ol-implementation-specification.md
 * (قسم 6، Testing Strategy) — الأرقام هنا تطابق البنود الأحد عشر صراحة.
 */
class OrganizationLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Organization, 2: Subscription, 3: User} [$owner, $org, $subscription, $member] */
    private function setupActiveOrgWithSeat(int $seatLimit = 5): array
    {
        $this->seed(MarketplaceCatalogSeeder::class);

        $owner = User::factory()->create();
        $organization = Organization::create(['name' => 'مؤسسة اختبار OL', 'type' => 'firm', 'owner_id' => $owner->id]);
        Membership::create(['user_id' => $owner->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Owner]);

        $member = User::factory()->create();
        Membership::create(['user_id' => $member->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);

        $item = MarketplaceItem::where('key', 'marefa')->firstOrFail();
        $item->update(['billing_model' => 'both']);

        $subscription = app(OrganizationSubscriptionService::class)->create($owner, $organization, $item, 'Professional', $seatLimit);
        app(SeatService::class)->assign($owner, $subscription, $member);

        return [$owner, $organization, $subscription, $member];
    }

    /** #1 — Archive بمؤسسة لديها Subscription: تُلغى صراحة. */
    public function test_1_archive_cancels_active_subscription(): void
    {
        [$owner, $organization, $subscription] = $this->setupActiveOrgWithSeat();

        app(OrganizationLifecycleService::class)->archive($owner, $organization);

        $this->assertSame('cancelled', $subscription->fresh()->status);
        $this->assertSame('archived', $organization->fresh()->status);
    }

    /** #2 — Archive بمؤسسة لديها Seats: تُحرَّر تلقائيًا. */
    public function test_2_archive_releases_active_seats(): void
    {
        [$owner, $organization, $subscription] = $this->setupActiveOrgWithSeat();
        $this->assertSame(1, SubscriptionSeat::where('subscription_id', $subscription->id)->active()->count());

        app(OrganizationLifecycleService::class)->archive($owner, $organization);

        $this->assertSame(0, SubscriptionSeat::where('subscription_id', $subscription->id)->active()->count());
    }

    /** #3 — إبطال الوصول بعد Archive: EntitlementResolver يرفض فورًا، بلا أي كود إضافي. */
    public function test_3_access_is_denied_after_archive_via_entitlement_resolver(): void
    {
        [$owner, $organization, $subscription, $member] = $this->setupActiveOrgWithSeat();
        $item = MarketplaceItem::where('key', 'marefa')->firstOrFail();

        $before = app(EntitlementResolver::class)->resolve($member, $item, $organization);
        $this->assertTrue($before->allowed);

        app(OrganizationLifecycleService::class)->archive($owner, $organization);

        $after = app(EntitlementResolver::class)->resolve($member->fresh(), $item, $organization->fresh());
        $this->assertFalse($after->allowed);
    }

    /** #4 — لا إعادة وصول تلقائي بعد Restore. */
    public function test_4_restore_does_not_reactivate_subscription_or_access(): void
    {
        [$owner, $organization, $subscription] = $this->setupActiveOrgWithSeat();

        app(OrganizationLifecycleService::class)->archive($owner, $organization);
        app(OrganizationLifecycleService::class)->restore($owner, $organization);

        $this->assertSame('active', $organization->fresh()->status);
        $this->assertSame('cancelled', $subscription->fresh()->status, 'Restore لا يجوز يُعيد تفعيل اشتراك ملغى — روح AD-014');
        $this->assertSame(0, SubscriptionSeat::where('subscription_id', $subscription->id)->active()->count());
    }

    /** #5 — Restore لمؤسسة Archived: الحالة تعود Active فقط. */
    public function test_5_restore_returns_organization_to_active_status(): void
    {
        [$owner, $organization] = $this->setupActiveOrgWithSeat();
        app(OrganizationLifecycleService::class)->archive($owner, $organization);
        $this->assertSame('archived', $organization->fresh()->status);

        app(OrganizationLifecycleService::class)->restore($owner, $organization);

        $this->assertSame('active', $organization->fresh()->status);
    }

    /** #6 — Archive بواسطة Admin/Member غير مخوَّل: مرفوض. */
    public function test_6_archive_rejects_non_owner_actor(): void
    {
        [$owner, $organization] = $this->setupActiveOrgWithSeat();
        $adminUser = User::factory()->create();
        Membership::create(['user_id' => $adminUser->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Admin]);

        $this->expectException(AuthorizationException::class);

        app(OrganizationLifecycleService::class)->archive($adminUser, $organization);
    }

    public function test_6b_restore_rejects_non_owner_actor(): void
    {
        [$owner, $organization] = $this->setupActiveOrgWithSeat();
        app(OrganizationLifecycleService::class)->archive($owner, $organization);
        $memberUser = User::factory()->create();
        Membership::create(['user_id' => $memberUser->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);

        $this->expectException(AuthorizationException::class);

        app(OrganizationLifecycleService::class)->restore($memberUser, $organization);
    }

    /** #6c — Archive بواسطة عضو عادي (Lawyer، لا Admin) — مرفوض. */
    public function test_6c_archive_rejects_plain_member_actor(): void
    {
        [$owner, $organization] = $this->setupActiveOrgWithSeat();
        $plainMember = User::factory()->create();
        Membership::create(['user_id' => $plainMember->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);

        $this->expectException(AuthorizationException::class);
        app(OrganizationLifecycleService::class)->archive($plainMember, $organization);
    }

    /** #6d — Archive بواسطة مستخدم بلا أي Membership بهذي المؤسسة إطلاقًا — مرفوض. */
    public function test_6d_archive_rejects_actor_with_zero_membership(): void
    {
        [, $organization] = $this->setupActiveOrgWithSeat();
        $customer = User::factory()->create();

        $this->expectException(AuthorizationException::class);
        app(OrganizationLifecycleService::class)->archive($customer, $organization);
    }

    /**
     * #6e — Platform Authorization Foundation (Option D) وسّعت هذي الصلاحية
     * لتشمل Staff، ليس Owner فقط كما بالتصميم الأصلي — قرار لاحق مُعتمَد
     * صراحة، لا تعارض. هذا الاختبار يثبته على مؤسسة **عادية لها Owner حقيقي**
     * (لا يتيمة)، بمعزل عن اختبارات Attack Matrix المخصَّصة لسيناريو اليتيمة.
     */
    public function test_6e_platform_staff_can_archive_a_normally_owned_organization(): void
    {
        [, $organization] = $this->setupActiveOrgWithSeat();
        $staff = User::factory()->create(['is_platform_staff' => true]);

        app(OrganizationLifecycleService::class)->archive($staff, $organization);

        $this->assertSame('archived', $organization->fresh()->status);
    }

    /** #6f — Tenant Isolation/IDOR: Owner حقيقي لمؤسسة A لا يقدر يؤرشف مؤسسة B. */
    public function test_6f_owner_of_organization_a_cannot_archive_organization_b(): void
    {
        [$ownerA] = $this->setupActiveOrgWithSeat();
        [, $organizationB] = $this->setupActiveOrgWithSeat();

        $this->expectException(AuthorizationException::class);
        app(OrganizationLifecycleService::class)->archive($ownerA, $organizationB);
    }

    /**
     * #6g — تلاعب بـ`active_organization_id` بالجلسة: Authorization يعتمد
     * حصرًا على Membership الفعلية بالمؤسسة المُمرَّرة صراحة كمعامل، لا على
     * أي قيمة بالجلسة (AD-012). نضبط السياق النشط على مؤسسة أخرى (لا علاقة
     * للفاعل بها) للتأكد أن هذا لا يغيّر النتيجة بأي اتجاه.
     */
    public function test_6g_active_organization_context_session_value_does_not_influence_archive_authorization(): void
    {
        [$owner, $organization] = $this->setupActiveOrgWithSeat();
        [, $unrelatedOrganization] = $this->setupActiveOrgWithSeat();

        // الجلسة تشير لمؤسسة غير ذات علاقة بالفاعل إطلاقًا.
        session(['active_organization_id' => $unrelatedOrganization->id]);
        $this->assertNull(ActiveOrganizationContext::current(), 'الفاعل ليس عضوًا بالمؤسسة غير المرتبطة، فالسياق يبقى null رغم الجلسة.');

        // رغم ذلك، الأرشفة تنجح لأن الفاعل Owner حقيقي بالمؤسسة المُمرَّرة صراحة كمعامل.
        app(OrganizationLifecycleService::class)->archive($owner, $organization);
        $this->assertSame('archived', $organization->fresh()->status);
    }

    /** إضافي — Restore على مؤسسة Active بالفعل: No-op آمن، لا خطأ، لا فعل. */
    public function test_restore_on_already_active_organization_is_a_safe_noop(): void
    {
        [$owner, $organization] = $this->setupActiveOrgWithSeat();
        $this->assertSame('active', $organization->fresh()->status);

        app(OrganizationLifecycleService::class)->restore($owner, $organization);

        $this->assertSame('active', $organization->fresh()->status);
        $this->assertFalse(AuditLog::where('event', AuditEvent::OrganizationRestored->value)->where('subject_id', $organization->id)->exists());
    }

    // --- Livewire حقيقي — نفس الحاجز عبر الواجهة الفعلية، لا استدعاء مباشر فقط ---

    public function test_livewire_owner_can_archive_and_restore_via_edit_page(): void
    {
        [$owner, $organization] = $this->setupActiveOrgWithSeat();

        Livewire::actingAs($owner)
            ->test(EditOrganization::class, ['record' => $organization->id])
            ->callAction('archive');
        $this->assertTrue($organization->fresh()->isArchived());

        Livewire::actingAs($owner)
            ->test(EditOrganization::class, ['record' => $organization->id])
            ->callAction('restore');
        $this->assertFalse($organization->fresh()->isArchived());
    }

    public function test_livewire_plain_member_cannot_archive_via_edit_page(): void
    {
        [, $organization] = $this->setupActiveOrgWithSeat();
        $plainMember = User::factory()->create();
        Membership::create(['user_id' => $plainMember->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);

        Livewire::actingAs($plainMember)
            ->test(EditOrganization::class, ['record' => $organization->id])
            ->callAction('archive')
            ->assertNotified();

        $this->assertFalse($organization->fresh()->isArchived());
    }

    /** نفس الحماية على مسار Filament مختلف — زر الأرشفة بجدول القائمة، لا فقط صفحة التعديل. */
    public function test_livewire_table_action_archive_on_list_page_works_and_is_protected(): void
    {
        [$owner, $organization] = $this->setupActiveOrgWithSeat();
        [, $otherOrganization] = $this->setupActiveOrgWithSeat();

        Livewire::actingAs($owner)
            ->test(\App\Filament\Resources\OrganizationResource\Pages\ListOrganizations::class)
            ->callTableAction('archive', $organization);
        $this->assertTrue($organization->fresh()->isArchived());

        Livewire::actingAs($owner)
            ->test(\App\Filament\Resources\OrganizationResource\Pages\ListOrganizations::class)
            ->callTableAction('archive', $otherOrganization)
            ->assertNotified();
        $this->assertFalse($otherOrganization->fresh()->isArchived());
    }

    /** #7 — محاولة الوصول لمؤسسة Archived من مستخدم كان له مقعد فعليًا. */
    public function test_7_member_with_prior_seat_loses_access_after_archive(): void
    {
        [$owner, $organization, , $member] = $this->setupActiveOrgWithSeat();
        $item = MarketplaceItem::where('key', 'marefa')->firstOrFail();

        app(OrganizationLifecycleService::class)->archive($owner, $organization);

        $decision = app(EntitlementResolver::class)->resolve($member->fresh(), $item, $organization->fresh());
        $this->assertFalse($decision->allowed);
        $this->assertSame('needs_subscription', $decision->reason->value);
    }

    /** #8 — لا Orphan Subscription: صفر Subscription نشط لمؤسسة Archived. */
    public function test_8_no_active_subscription_remains_for_archived_organization(): void
    {
        [$owner, $organization] = $this->setupActiveOrgWithSeat();

        app(OrganizationLifecycleService::class)->archive($owner, $organization);

        $this->assertSame(
            0,
            Subscription::where('subscriber_type', 'organization')
                ->where('subscriber_id', $organization->id)
                ->where('status', 'active')
                ->count()
        );
    }

    /** #9 — Audit Events: OrganizationArchived/Restored مسجَّلان بالبيانات الصحيحة. */
    public function test_9_archive_and_restore_are_logged_to_audit_trail(): void
    {
        [$owner, $organization] = $this->setupActiveOrgWithSeat();

        app(OrganizationLifecycleService::class)->archive($owner, $organization);

        $archivedLog = AuditLog::where('event', AuditEvent::OrganizationArchived->value)
            ->where('subject_type', Organization::class)
            ->where('subject_id', $organization->id)
            ->first();
        $this->assertNotNull($archivedLog);
        $this->assertSame($organization->id, $archivedLog->organization_id);
        $this->assertSame($owner->id, $archivedLog->actor_user_id);

        app(OrganizationLifecycleService::class)->restore($owner, $organization);

        $restoredLog = AuditLog::where('event', AuditEvent::OrganizationRestored->value)
            ->where('subject_type', Organization::class)
            ->where('subject_id', $organization->id)
            ->first();
        $this->assertNotNull($restoredLog);
        $this->assertSame($owner->id, $restoredLog->actor_user_id);
    }

    /** إضافي — Idempotency تسلسلية: أرشفة مؤسسة مؤرشَفة بالفعل لا فعل، لا خطأ، لا حدث Audit مكرَّر. */
    public function test_archive_is_idempotent(): void
    {
        [$owner, $organization] = $this->setupActiveOrgWithSeat();

        app(OrganizationLifecycleService::class)->archive($owner, $organization);
        $countAfterFirst = AuditLog::where('event', AuditEvent::OrganizationArchived->value)->count();

        app(OrganizationLifecycleService::class)->archive($owner, $organization);
        $countAfterSecond = AuditLog::where('event', AuditEvent::OrganizationArchived->value)->count();

        $this->assertSame($countAfterFirst, $countAfterSecond);
        $this->assertSame('archived', $organization->fresh()->status);
    }

    /** #10 — Concurrency: تُختبَر بعملية OS حقيقية منفصلة تمامًا — راجع تقرير الإكمال للدليل التجريبي. */

    /** #11 (تكميلي هنا) — تأكيد سريع إن Regression الكامل لم يُكسَر يبقى بمسؤولية suite الكاملة، لا هذا الملف وحده. */

    /**
     * AD-018 — يحل محل الاختبار السابق (كان يوثّق فجوة مقبولة: assign() على
     * اشتراك ملغى ينجح، EntitlementResolver وحده يمنع الوصول). الآن
     * OrganizationMarketplaceAccessGuard يرفض المحاولة من جذرها — دفاع مزدوج
     * حقيقي (Domain State + Entitlement)، لا اعتماد على طبقة واحدة فقط.
     */
    public function test_seat_cannot_be_assigned_to_a_subscription_of_an_archived_organization(): void
    {
        [$owner, $organization, $subscription] = $this->setupActiveOrgWithSeat();

        app(OrganizationLifecycleService::class)->archive($owner, $organization);
        $this->assertSame('cancelled', $subscription->fresh()->status);

        $newMember = User::factory()->create();
        Membership::create(['user_id' => $newMember->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);

        $this->expectException(\InvalidArgumentException::class);
        app(SeatService::class)->assign($owner, $subscription->fresh(), $newMember);
    }
}
