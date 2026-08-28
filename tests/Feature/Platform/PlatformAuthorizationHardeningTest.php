<?php

namespace Tests\Feature\Platform;

use App\Enums\AuditEvent;
use App\Enums\MembershipRole;
use App\Filament\Resources\OrganizationResource\Pages\EditOrganization;
use App\Filament\Resources\OrganizationResource\RelationManagers\MembershipsRelationManager;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\OrganizationSubscriptionService;
use App\Services\SeatService;
use Database\Seeders\MarketplaceCatalogSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Security Hardening Pass — إثبات مباشر لإغلاق Finding #1 (Membership
 * CreateAction) وFinding #2 (SeatService) من
 * docs/platform-authorization-security-review.md، شاملًا اختبار Livewire
 * حقيقي (الفجوة اللي فاتت الجولة الأولى بالضبط لأنها لم تُختبَر). راجع
 * docs/platform-authorization-hardening-completion-report.md.
 */
class PlatformAuthorizationHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function organizationWithOwner(string $name = 'مكتب أ'): array
    {
        $owner = User::factory()->create(['is_platform_staff' => false]);
        $organization = Organization::create(['name' => $name, 'type' => 'firm', 'owner_id' => $owner->id]);
        Membership::create(['user_id' => $owner->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Owner]);

        return [$owner, $organization];
    }

    // --- 1. Staff creating Owner is only allowed via the authorized path (orphaned org), and audited ---

    public function test_1_staff_can_bootstrap_first_owner_for_organization_with_no_owner_at_all_and_it_is_audited(): void
    {
        $orphanOrg = Organization::create(['name' => 'مؤسسة يتيمة', 'type' => 'firm', 'owner_id' => null]);
        $staff = User::factory()->create(['is_platform_staff' => true]);
        $target = User::factory()->create();

        $membership = app(MembershipService::class)->add($staff, $orphanOrg, $target, MembershipRole::Owner);

        $this->assertSame(MembershipRole::Owner, $membership->role);
        $this->assertTrue(
            AuditLog::where('event', AuditEvent::MembershipCreated->value)
                ->where('subject_id', $membership->id)
                ->where('actor_user_id', $staff->id)
                ->exists()
        );
    }

    // --- 2. Customer cannot create a Membership directly ---

    public function test_2_customer_cannot_create_membership_directly(): void
    {
        [, $organization] = $this->organizationWithOwner();
        $customer = User::factory()->create(['is_platform_staff' => false]);
        $target = User::factory()->create();

        $this->expectException(AuthorizationException::class);
        app(MembershipService::class)->add($customer, $organization, $target, MembershipRole::Lawyer);
    }

    // --- 3. Member cannot create a Membership in an organization they don't manage ---

    public function test_3_member_cannot_create_membership_in_organization_they_do_not_manage(): void
    {
        [, $orgA] = $this->organizationWithOwner('مكتب أ');
        [, $orgB] = $this->organizationWithOwner('مكتب ب');

        $memberOfA = User::factory()->create(['is_platform_staff' => false]);
        Membership::create(['user_id' => $memberOfA->id, 'organization_id' => $orgA->id, 'role' => MembershipRole::Lawyer]);

        $target = User::factory()->create();

        $this->expectException(AuthorizationException::class);
        app(MembershipService::class)->add($memberOfA, $orgB, $target, MembershipRole::Lawyer);
    }

    // --- 4. Staff cannot grant Owner to self (or anyone) via any Filament path when the org already has a real owner ---

    public function test_4_staff_cannot_grant_owner_to_self_via_create_when_organization_already_has_owner(): void
    {
        [, $organization] = $this->organizationWithOwner();
        $staff = User::factory()->create(['is_platform_staff' => true]);

        $this->expectException(AuthorizationException::class);
        app(MembershipService::class)->add($staff, $organization, $staff, MembershipRole::Owner);
    }

    public function test_4b_staff_cannot_promote_self_to_owner_via_change_role_when_organization_already_has_owner(): void
    {
        [, $organization] = $this->organizationWithOwner();
        $staff = User::factory()->create(['is_platform_staff' => true]);
        $staffMembership = Membership::create(['user_id' => $staff->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);

        $this->expectException(AuthorizationException::class);
        app(MembershipService::class)->changeRole($staff, $staffMembership, MembershipRole::Owner);
    }

    public function test_4c_staff_cannot_grant_owner_to_a_third_party_when_organization_already_has_owner(): void
    {
        [, $organization] = $this->organizationWithOwner();
        $staff = User::factory()->create(['is_platform_staff' => true]);
        $accomplice = User::factory()->create();

        $this->expectException(AuthorizationException::class);
        app(MembershipService::class)->add($staff, $organization, $accomplice, MembershipRole::Owner);
    }

    // --- 5. Revoking Staff does not need to "reveal" anything — creation was rejected up front, no row ever existed ---

    public function test_5_rejected_owner_grant_attempt_leaves_no_membership_row_and_survives_staff_revocation_check(): void
    {
        [, $organization] = $this->organizationWithOwner();
        $staff = User::factory()->create(['is_platform_staff' => true]);

        try {
            app(MembershipService::class)->add($staff, $organization, $staff, MembershipRole::Owner);
        } catch (AuthorizationException) {
            // متوقَّع.
        }

        $this->assertFalse(
            Membership::where('user_id', $staff->id)->where('organization_id', $organization->id)->exists()
        );

        $staff->forceFill(['is_platform_staff' => false])->save();

        $this->assertFalse(
            Membership::where('user_id', $staff->id)->where('organization_id', $organization->id)->where('role', MembershipRole::Owner)->exists()
        );
    }

    // --- 6. Direct invocation of MembershipService (no Filament, no HTTP) rejects unauthorized actors ---

    public function test_6_direct_membership_service_invocation_rejects_unauthorized_actor(): void
    {
        [, $organization] = $this->organizationWithOwner();
        $customer = User::factory()->create(['is_platform_staff' => false]);
        $customerMembership = Membership::create(['user_id' => $customer->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);

        $this->expectException(AuthorizationException::class);
        app(MembershipService::class)->changeRole($customer, $customerMembership, MembershipRole::Admin);
    }

    // --- 7. Direct invocation of SeatService (no Filament, no HTTP) rejects unauthorized actors ---

    public function test_7_direct_seat_service_invocation_rejects_unauthorized_actor(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        [$owner, $organization] = $this->organizationWithOwner();
        $item = \App\Models\MarketplaceItem::where('key', 'marefa')->firstOrFail();
        $item->update(['billing_model' => 'both']);
        $subscription = app(OrganizationSubscriptionService::class)->create($owner, $organization, $item, 'Professional', 5);

        $customer = User::factory()->create(['is_platform_staff' => false]);
        $target = User::factory()->create();
        Membership::create(['user_id' => $target->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);

        $this->expectException(AuthorizationException::class);
        app(SeatService::class)->assign($customer, $subscription, $target);
    }

    public function test_7b_direct_seat_service_release_rejects_unauthorized_actor(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        [$owner, $organization] = $this->organizationWithOwner();
        $item = \App\Models\MarketplaceItem::where('key', 'marefa')->firstOrFail();
        $item->update(['billing_model' => 'both']);
        $subscription = app(OrganizationSubscriptionService::class)->create($owner, $organization, $item, 'Professional', 5);

        $target = User::factory()->create();
        Membership::create(['user_id' => $target->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);
        $seat = app(SeatService::class)->assign($owner, $subscription, $target);

        $customer = User::factory()->create(['is_platform_staff' => false]);

        $this->expectException(AuthorizationException::class);
        app(SeatService::class)->release($customer, $seat);
    }

    /**
     * تثبت أن performRelease() الداخلية (المُستخدَمة بواسطة تنظيف
     * MembershipRevoked النظامي) ما زالت تعمل بلا Authorization خاص —
     * قرار مصمَّم، ليس فجوة (راجع تعليق SeatService::class أعلى الملف).
     */
    public function test_7c_system_seat_cleanup_after_membership_removal_still_works(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        [$owner, $organization] = $this->organizationWithOwner();
        $item = \App\Models\MarketplaceItem::where('key', 'marefa')->firstOrFail();
        $item->update(['billing_model' => 'both']);
        $subscription = app(OrganizationSubscriptionService::class)->create($owner, $organization, $item, 'Professional', 5);

        $target = User::factory()->create();
        $targetMembership = Membership::create(['user_id' => $target->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);
        $seat = app(SeatService::class)->assign($owner, $subscription, $target);

        app(MembershipService::class)->remove($owner, $targetMembership);

        $this->assertSame('released', $seat->fresh()->status);
    }

    // --- 8. Livewire bypass attempt does not escape Domain Authorization (the exact blind spot from review #1) ---

    public function test_8_livewire_create_action_by_unauthorized_actor_is_rejected_and_creates_no_membership(): void
    {
        [, $organization] = $this->organizationWithOwner();
        $customer = User::factory()->create(['is_platform_staff' => false]);
        $target = User::factory()->create();

        $countBefore = Membership::count();

        Livewire::actingAs($customer)
            ->test(MembershipsRelationManager::class, [
                'ownerRecord' => $organization,
                'pageClass' => EditOrganization::class,
            ])
            ->callTableAction('create', data: ['user_id' => $target->id, 'role' => MembershipRole::Lawyer->value])
            ->assertNotified();

        $this->assertSame($countBefore, Membership::count());
    }

    public function test_8b_livewire_create_action_by_staff_granting_owner_on_already_owned_org_is_rejected(): void
    {
        [, $organization] = $this->organizationWithOwner();
        $staff = User::factory()->create(['is_platform_staff' => true]);

        $ownerCountBefore = Membership::where('organization_id', $organization->id)->where('role', MembershipRole::Owner)->count();

        Livewire::actingAs($staff)
            ->test(MembershipsRelationManager::class, [
                'ownerRecord' => $organization,
                'pageClass' => EditOrganization::class,
            ])
            ->callTableAction('create', data: ['user_id' => $staff->id, 'role' => MembershipRole::Owner->value])
            ->assertNotified();

        $this->assertSame(
            $ownerCountBefore,
            Membership::where('organization_id', $organization->id)->where('role', MembershipRole::Owner)->count()
        );
    }

    /** إثبات إيجابي: نفس المسار يعمل فعليًا لفاعل مخوَّل (Owner يضيف عضوًا). */
    public function test_8c_livewire_create_action_by_authorized_owner_succeeds(): void
    {
        [$owner, $organization] = $this->organizationWithOwner();
        $target = User::factory()->create();

        Livewire::actingAs($owner)
            ->test(MembershipsRelationManager::class, [
                'ownerRecord' => $organization,
                'pageClass' => EditOrganization::class,
            ])
            ->callTableAction('create', data: ['user_id' => $target->id, 'role' => MembershipRole::Lawyer->value]);

        $this->assertTrue(
            Membership::where('user_id', $target->id)->where('organization_id', $organization->id)->exists()
        );
    }

    // --- 9. Authorized Owner/Admin continue to work as expected (regression guard) ---

    public function test_9_authorized_owner_can_add_member_and_grant_co_ownership(): void
    {
        [$owner, $organization] = $this->organizationWithOwner();
        $newMember = User::factory()->create();
        $coOwnerCandidate = User::factory()->create();

        $membership = app(MembershipService::class)->add($owner, $organization, $newMember, MembershipRole::Lawyer);
        $this->assertSame(MembershipRole::Lawyer, $membership->role);

        $coOwner = app(MembershipService::class)->add($owner, $organization, $coOwnerCandidate, MembershipRole::Owner);
        $this->assertSame(MembershipRole::Owner, $coOwner->role);
        $this->assertSame(2, Membership::where('organization_id', $organization->id)->where('role', MembershipRole::Owner)->count());
    }

    public function test_9a_owner_promoting_existing_member_to_owner_via_change_role_is_audited(): void
    {
        [$owner, $organization] = $this->organizationWithOwner();
        $member = User::factory()->create();
        $membership = Membership::create(['user_id' => $member->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Admin]);

        app(MembershipService::class)->changeRole($owner, $membership, MembershipRole::Owner);

        $this->assertSame(MembershipRole::Owner, $membership->fresh()->role);
        $this->assertTrue(
            AuditLog::where('event', AuditEvent::OwnershipGranted->value)
                ->where('subject_id', $membership->id)
                ->where('actor_user_id', $owner->id)
                ->exists()
        );
    }

    public function test_9b_authorized_admin_can_add_non_owner_member_but_not_owner(): void
    {
        [, $organization] = $this->organizationWithOwner();
        $admin = User::factory()->create();
        Membership::create(['user_id' => $admin->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Admin]);
        $target = User::factory()->create();

        $membership = app(MembershipService::class)->add($admin, $organization, $target, MembershipRole::Lawyer);
        $this->assertSame(MembershipRole::Lawyer, $membership->role);

        $anotherTarget = User::factory()->create();
        $this->expectException(AuthorizationException::class);
        app(MembershipService::class)->add($admin, $organization, $anotherTarget, MembershipRole::Owner);
    }

    // --- 10. No regression on duplicate-membership DB constraint ---

    public function test_10_cannot_create_duplicate_membership_for_same_user_and_organization(): void
    {
        [$owner, $organization] = $this->organizationWithOwner();
        $target = User::factory()->create();
        app(MembershipService::class)->add($owner, $organization, $target, MembershipRole::Lawyer);

        $this->expectException(InvalidArgumentException::class);
        app(MembershipService::class)->add($owner, $organization, $target, MembershipRole::Admin);
    }

    // --- Attack مهم جدًا: End-to-End — منع الإنشاء أصلًا، لا تنظيف لاحق فقط ---

    public function test_e2e_platform_staff_cannot_bootstrap_permanent_ownership_by_self_granting_then_surviving_revocation(): void
    {
        [, $organization] = $this->organizationWithOwner('مؤسسة مُدارة فعليًا');
        $staff = User::factory()->create(['is_platform_staff' => true]);

        $exceptionWasThrown = false;
        try {
            app(MembershipService::class)->add($staff, $organization, $staff, MembershipRole::Owner);
        } catch (AuthorizationException) {
            $exceptionWasThrown = true;
        }

        // الخطوة الحاسمة: الرفض حدث *أثناء* محاولة الإنشاء، لا بعدها.
        $this->assertTrue($exceptionWasThrown, 'يجب أن تُرفَض محاولة Staff منح نفسه Owner فورًا، لا أن تُقبَل ثم تُكتشَف لاحقًا.');
        $this->assertSame(0, Membership::where('user_id', $staff->id)->where('organization_id', $organization->id)->count());

        // حتى لو حاولنا سحب Staff الآن، لا شيء "يُكشَف" لأنه لم يُنشَأ شيء أصلًا.
        $staff->forceFill(['is_platform_staff' => false])->save();
        $this->assertFalse($staff->fresh()->isPlatformStaff());
        $this->assertSame(1, Membership::where('organization_id', $organization->id)->where('role', MembershipRole::Owner)->count());
    }

    // ============================================================
    // AD-017 — transferOwnership() الآن تحت نفس authorizeGrantingOwnership()
    // راجع docs/ownership-transfer-security-hardening-design.md §5.
    // ============================================================

    /**
     * السيناريو المحوري بالضبط اللي اكتشفه Security Review #2 (Finding H1)،
     * الآن Regression دائم. مطلوب صراحة: لا يتغير Owner، لا OwnershipGranted،
     * الرفض عبر استدعاء مباشر بمعزل عن Filament.
     */
    public function test_staff_cannot_transfer_ownership_to_self_via_direct_service_call(): void
    {
        [$realOwner, $organization] = $this->organizationWithOwner();
        $realOwnerMembership = Membership::where('user_id', $realOwner->id)->where('organization_id', $organization->id)->first();
        $staff = User::factory()->create(['is_platform_staff' => true]);

        $staffMembership = app(MembershipService::class)->add($staff, $organization, $staff, MembershipRole::Admin);

        $auditCountBefore = AuditLog::where('event', AuditEvent::OwnershipGranted->value)->count();

        $this->expectException(AuthorizationException::class);

        try {
            app(MembershipService::class)->transferOwnership($staff, $realOwnerMembership, $staffMembership, MembershipRole::Admin);
        } finally {
            $this->assertSame(MembershipRole::Owner, $realOwnerMembership->fresh()->role, 'لا يجوز يتغيّر المالك الحقيقي.');
            $this->assertSame(MembershipRole::Admin, $staffMembership->fresh()->role, 'لا يجوز Staff يصبح Owner.');
            $this->assertSame($auditCountBefore, AuditLog::where('event', AuditEvent::OwnershipGranted->value)->count(), 'لا OwnershipGranted جديد.');
        }
    }

    /** نفس السيناريو، عبر زر "نقل الملكية" الحقيقي بـFilament/Livewire — لا Bypass عبر الواجهة. */
    public function test_staff_cannot_transfer_ownership_via_livewire_action(): void
    {
        [$realOwner, $organization] = $this->organizationWithOwner();
        $realOwnerMembership = Membership::where('user_id', $realOwner->id)->where('organization_id', $organization->id)->first();
        $staff = User::factory()->create(['is_platform_staff' => true]);
        $staffMembership = app(MembershipService::class)->add($staff, $organization, $staff, MembershipRole::Admin);

        Livewire::actingAs($staff)
            ->test(MembershipsRelationManager::class, [
                'ownerRecord' => $organization,
                'pageClass' => EditOrganization::class,
            ])
            ->callTableAction('transferOwnership', $realOwnerMembership, data: [
                'to_membership_id' => $staffMembership->id,
                'demote_from_to' => MembershipRole::Admin->value,
            ])
            ->assertNotified();

        $this->assertSame(MembershipRole::Owner, $realOwnerMembership->fresh()->role);
        $this->assertSame(MembershipRole::Admin, $staffMembership->fresh()->role);
    }

    /** إعادة تأكيد صريحة: المؤسسة اليتيمة تبقى الاستثناء الوحيد المسموح. */
    public function test_staff_can_still_bootstrap_ownership_for_orphaned_organization(): void
    {
        $orphanOrg = Organization::create(['name' => 'مؤسسة يتيمة أخرى', 'type' => 'firm', 'owner_id' => null]);
        $staff = User::factory()->create(['is_platform_staff' => true]);
        $eligibleMember = User::factory()->create();

        $membership = app(MembershipService::class)->add($staff, $orphanOrg, $eligibleMember, MembershipRole::Owner);

        $this->assertSame(MembershipRole::Owner, $membership->fresh()->role);
        $this->assertSame(1, Membership::where('organization_id', $orphanOrg->id)->where('role', MembershipRole::Owner)->count());
        $this->assertTrue(AuditLog::where('event', AuditEvent::MembershipCreated->value)->where('subject_id', $membership->id)->exists());
    }

    /** Regression: Owner حقيقي ينقل ملكيته لعضو آخر بنفس مؤسسته — يبقى يعمل. */
    public function test_real_owner_can_still_transfer_ownership(): void
    {
        [$owner, $organization] = $this->organizationWithOwner();
        $ownerMembership = Membership::where('user_id', $owner->id)->where('organization_id', $organization->id)->first();
        $newOwnerUser = User::factory()->create();
        $newOwnerMembership = Membership::create(['user_id' => $newOwnerUser->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);

        app(MembershipService::class)->transferOwnership($owner, $ownerMembership, $newOwnerMembership, MembershipRole::Admin);

        $this->assertSame(MembershipRole::Owner, $newOwnerMembership->fresh()->role);
        $this->assertSame(MembershipRole::Admin, $ownerMembership->fresh()->role);
    }

    /** Regression: Admin لا يزال مرفوضًا (كان مرفوضًا أصلًا، لم يتغيّر). */
    public function test_admin_still_cannot_transfer_ownership(): void
    {
        [$owner, $organization] = $this->organizationWithOwner();
        $ownerMembership = Membership::where('user_id', $owner->id)->where('organization_id', $organization->id)->first();
        $admin = User::factory()->create();
        $adminMembership = Membership::create(['user_id' => $admin->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Admin]);

        $this->expectException(AuthorizationException::class);
        app(MembershipService::class)->transferOwnership($admin, $ownerMembership, $adminMembership);
    }

    /**
     * تجميع صريح: الأبواب الثلاثة (add/changeRole/transferOwnership) مغلقة
     * بنفس القاعدة، بنفس الوقت — يمنع "أصلحنا بابًا، فتح غيره" مستقبلًا
     * (بالضبط النمط اللي تكرَّر مرتين بهذي المرحلة).
     */
    public function test_staff_cannot_bypass_via_create_or_change_role_either(): void
    {
        [$realOwner, $organization] = $this->organizationWithOwner();
        $realOwnerMembership = Membership::where('user_id', $realOwner->id)->where('organization_id', $organization->id)->first();
        $staff = User::factory()->create(['is_platform_staff' => true]);

        $doorsRejected = [];

        try {
            app(MembershipService::class)->add($staff, $organization, $staff, MembershipRole::Owner);
        } catch (AuthorizationException) {
            $doorsRejected[] = 'add';
        }

        $staffMembership = Membership::create(['user_id' => $staff->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Admin]);

        try {
            app(MembershipService::class)->changeRole($staff, $staffMembership, MembershipRole::Owner);
        } catch (AuthorizationException) {
            $doorsRejected[] = 'changeRole';
        }

        try {
            app(MembershipService::class)->transferOwnership($staff, $realOwnerMembership, $staffMembership, MembershipRole::Admin);
        } catch (AuthorizationException) {
            $doorsRejected[] = 'transferOwnership';
        }

        $this->assertSame(['add', 'changeRole', 'transferOwnership'], $doorsRejected, 'الأبواب الثلاثة يجب تُرفَض كلها.');
        $this->assertSame(MembershipRole::Owner, $realOwnerMembership->fresh()->role);
        $this->assertSame(MembershipRole::Admin, $staffMembership->fresh()->role);
    }
}
