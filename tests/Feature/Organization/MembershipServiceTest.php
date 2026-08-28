<?php

namespace Tests\Feature\Organization;

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\SubscriptionSeat;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Phase OI — Owner Integrity. راجع docs/phase-oi-owner-integrity-implementation-specification.md.
 */
class MembershipServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrgWithOwner(): array
    {
        $owner = User::factory()->create();
        $organization = Organization::create(['name' => 'مكتب اختبار', 'type' => 'firm', 'owner_id' => $owner->id]);
        $ownerMembership = Membership::create(['user_id' => $owner->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Owner]);

        return [$owner, $organization, $ownerMembership];
    }

    // --- Last Owner Rule ---

    public function test_change_role_rejects_demoting_the_last_owner(): void
    {
        [$owner, , $ownerMembership] = $this->makeOrgWithOwner();

        $this->expectException(InvalidArgumentException::class);

        app(MembershipService::class)->changeRole($owner, $ownerMembership, MembershipRole::Admin);
    }

    public function test_remove_rejects_removing_the_last_owner(): void
    {
        [$owner, , $ownerMembership] = $this->makeOrgWithOwner();

        $this->expectException(InvalidArgumentException::class);

        app(MembershipService::class)->remove($owner, $ownerMembership);
    }

    public function test_change_role_allows_demoting_an_owner_when_another_owner_exists(): void
    {
        [$owner, $organization, $ownerMembership] = $this->makeOrgWithOwner();
        $secondOwnerUser = User::factory()->create();
        Membership::create(['user_id' => $secondOwnerUser->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Owner]);

        app(MembershipService::class)->changeRole($owner, $ownerMembership, MembershipRole::Admin);

        $this->assertSame(MembershipRole::Admin, $ownerMembership->fresh()->role);
        $this->assertSame(1, Membership::where('organization_id', $organization->id)->where('role', MembershipRole::Owner)->count());
    }

    public function test_remove_allows_removing_an_owner_when_another_owner_exists(): void
    {
        [$owner, $organization, $ownerMembership] = $this->makeOrgWithOwner();
        $secondOwnerUser = User::factory()->create();
        Membership::create(['user_id' => $secondOwnerUser->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Owner]);

        app(MembershipService::class)->remove($owner, $ownerMembership);

        $this->assertNull($ownerMembership->fresh());
        $this->assertSame(1, Membership::where('organization_id', $organization->id)->where('role', MembershipRole::Owner)->count());
    }

    public function test_change_role_and_remove_do_not_require_last_owner_check_for_non_owner_roles(): void
    {
        [$owner, $organization] = $this->makeOrgWithOwner();
        $memberUser = User::factory()->create();
        $membership = Membership::create(['user_id' => $memberUser->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);

        app(MembershipService::class)->changeRole($owner, $membership, MembershipRole::Accountant);
        $this->assertSame(MembershipRole::Accountant, $membership->fresh()->role);

        app(MembershipService::class)->remove($owner, $membership);
        $this->assertNull($membership->fresh());
    }

    /** إزالة عضو (Membership::delete() حقيقي عبر Service) يبقى يُطلِق نفس تنظيف المقاعد الموجود أصلًا (BR-2B-04). */
    public function test_remove_still_releases_seats_via_membership_revoked_event(): void
    {
        [$owner, $organization] = $this->makeOrgWithOwner();
        $memberUser = User::factory()->create();
        $membership = Membership::create(['user_id' => $memberUser->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);

        $this->seed(\Database\Seeders\MarketplaceCatalogSeeder::class);
        $item = \App\Models\MarketplaceItem::where('key', 'marefa')->firstOrFail();
        $item->update(['billing_model' => 'both']);
        $subscription = app(\App\Services\OrganizationSubscriptionService::class)->create($owner, $organization, $item, 'Professional', 5);
        app(\App\Services\SeatService::class)->assign($owner, $subscription, $memberUser);

        $this->assertSame(1, SubscriptionSeat::where('subscription_id', $subscription->id)->active()->count());

        app(MembershipService::class)->remove($owner, $membership);

        $this->assertSame(0, SubscriptionSeat::where('subscription_id', $subscription->id)->active()->count());
    }

    // --- Transfer Ownership ---

    public function test_transfer_ownership_moves_role_atomically(): void
    {
        [$owner, $organization, $ownerMembership] = $this->makeOrgWithOwner();
        $newOwnerUser = User::factory()->create();
        $newOwnerMembership = Membership::create(['user_id' => $newOwnerUser->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);

        app(MembershipService::class)->transferOwnership($owner, $ownerMembership, $newOwnerMembership, MembershipRole::Admin);

        $this->assertSame(MembershipRole::Owner, $newOwnerMembership->fresh()->role);
        $this->assertSame(MembershipRole::Admin, $ownerMembership->fresh()->role);
        $this->assertSame(1, Membership::where('organization_id', $organization->id)->where('role', MembershipRole::Owner)->count());
    }

    public function test_transfer_ownership_rejects_cross_organization_transfer(): void
    {
        [$owner, , $ownerMembership] = $this->makeOrgWithOwner();
        $otherOrgUser = User::factory()->create();
        $otherOrg = Organization::create(['name' => 'مؤسسة أخرى', 'type' => 'firm']);
        $otherMembership = Membership::create(['user_id' => $otherOrgUser->id, 'organization_id' => $otherOrg->id, 'role' => MembershipRole::Lawyer]);

        $this->expectException(InvalidArgumentException::class);

        app(MembershipService::class)->transferOwnership($owner, $ownerMembership, $otherMembership);
    }

    public function test_transfer_ownership_rejects_demoting_to_owner_role(): void
    {
        [$owner, $organization, $ownerMembership] = $this->makeOrgWithOwner();
        $newOwnerUser = User::factory()->create();
        $newOwnerMembership = Membership::create(['user_id' => $newOwnerUser->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);

        $this->expectException(InvalidArgumentException::class);

        app(MembershipService::class)->transferOwnership($owner, $ownerMembership, $newOwnerMembership, MembershipRole::Owner);
    }

    public function test_transfer_ownership_allows_multiple_owners_to_coexist_afterward(): void
    {
        [$owner, $organization, $ownerMembership] = $this->makeOrgWithOwner();
        $secondOwnerUser = User::factory()->create();
        $secondOwnerMembership = Membership::create(['user_id' => $secondOwnerUser->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Owner]);
        $thirdUser = User::factory()->create();
        $thirdMembership = Membership::create(['user_id' => $thirdUser->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);

        // نقل من Owner الأول لعضو عادي، مع إبقاء Owner الثاني كما هو — تعدد Owners مسموح.
        app(MembershipService::class)->transferOwnership($owner, $ownerMembership, $thirdMembership, MembershipRole::Admin);

        $this->assertSame(MembershipRole::Owner, $thirdMembership->fresh()->role);
        $this->assertSame(MembershipRole::Owner, $secondOwnerMembership->fresh()->role);
        $this->assertSame(2, Membership::where('organization_id', $organization->id)->where('role', MembershipRole::Owner)->count());
    }

    // --- Authorization (Backend، لا UI) ---

    public function test_change_role_rejects_actor_without_membership(): void
    {
        [, $organization] = $this->makeOrgWithOwner();
        $memberUser = User::factory()->create();
        $membership = Membership::create(['user_id' => $memberUser->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);
        $outsider = User::factory()->create();

        $this->expectException(AuthorizationException::class);

        app(MembershipService::class)->changeRole($outsider, $membership, MembershipRole::Admin);
    }

    public function test_change_role_rejects_actor_with_non_admin_non_owner_role(): void
    {
        [, $organization] = $this->makeOrgWithOwner();
        $lawyerUser = User::factory()->create();
        $lawyerMembership = Membership::create(['user_id' => $lawyerUser->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);
        $anotherMemberUser = User::factory()->create();
        $anotherMembership = Membership::create(['user_id' => $anotherMemberUser->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Client]);

        $this->expectException(AuthorizationException::class);

        app(MembershipService::class)->changeRole($lawyerUser, $anotherMembership, MembershipRole::Admin);
    }

    public function test_change_role_allows_admin_actor(): void
    {
        [, $organization] = $this->makeOrgWithOwner();
        $adminUser = User::factory()->create();
        Membership::create(['user_id' => $adminUser->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Admin]);
        $memberUser = User::factory()->create();
        $membership = Membership::create(['user_id' => $memberUser->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);

        app(MembershipService::class)->changeRole($adminUser, $membership, MembershipRole::Accountant);

        $this->assertSame(MembershipRole::Accountant, $membership->fresh()->role);
    }

    public function test_transfer_ownership_rejects_admin_actor_only_owner_allowed(): void
    {
        [, $organization, $ownerMembership] = $this->makeOrgWithOwner();
        $adminUser = User::factory()->create();
        Membership::create(['user_id' => $adminUser->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Admin]);
        $targetUser = User::factory()->create();
        $targetMembership = Membership::create(['user_id' => $targetUser->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);

        $this->expectException(AuthorizationException::class);

        app(MembershipService::class)->transferOwnership($adminUser, $ownerMembership, $targetMembership);
    }

    public function test_transfer_ownership_allows_owner_actor(): void
    {
        [$owner, $organization, $ownerMembership] = $this->makeOrgWithOwner();
        $targetUser = User::factory()->create();
        $targetMembership = Membership::create(['user_id' => $targetUser->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);

        app(MembershipService::class)->transferOwnership($owner, $ownerMembership, $targetMembership);

        $this->assertSame(MembershipRole::Owner, $targetMembership->fresh()->role);
    }

    // --- Concurrency ---

    /**
     * الإثبات التسلسلي المباشر لمنطق العدّ اللي يمنع الوصول لصفر Owner:
     * مؤسسة بمالكين، إزالة الأول تنجح (يبقى مالك واحد)، محاولة إزالة الثاني
     * تُرفَض تحديدًا بـInvalidArgumentException (منطق assertNotLastOwner
     * نفسه، لا مجرد قفل قاعدة بيانات). يكمّل هذا الاختبار الدليل التجريبي
     * الحقيقي (عمليتا OS منفصلتان فعليًا، موثَّق بتقرير الإكمال) الذي أثبت
     * إن النتيجة النهائية لا تصل أبدًا لصفر Owner تحت تزامن حقيقي — هنا
     * نثبت تحديدًا *لماذا*: نفس فحص العدّ يعمل صح لحظة إعادة تقييمه.
     */
    public function test_last_owner_check_reflects_freshly_committed_state_sequentially(): void
    {
        [$owner, $organization, $ownerMembership] = $this->makeOrgWithOwner();
        $secondOwnerUser = User::factory()->create();
        $secondOwnerMembership = Membership::create(['user_id' => $secondOwnerUser->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Owner]);

        app(MembershipService::class)->remove($owner, $ownerMembership);
        $this->assertSame(1, Membership::where('organization_id', $organization->id)->where('role', MembershipRole::Owner)->count());

        $this->expectException(InvalidArgumentException::class);
        app(MembershipService::class)->remove($secondOwnerUser, $secondOwnerMembership);
    }
}
