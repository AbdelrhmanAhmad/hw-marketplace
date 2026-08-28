<?php

namespace Tests\Feature\Platform;

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\OrganizationLifecycleService;
use App\Services\OrganizationSubscriptionService;
use Database\Seeders\MarketplaceCatalogSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Platform Authorization Foundation — إثبات مباشر لكل سيناريو من الـAttack
 * Matrix بقسم 7 من docs/platform-authorization-foundation-specification.md.
 * كل اختبار هنا يثبت أن الرفض/القبول يحدث Backend-side (داخل Policy/Service)،
 * لا اعتمادًا على إخفاء واجهة أو مسار Filament فقط. هذا الملف هو المرجع
 * المباشر لتقرير Attack Matrix Results بالتقرير الختامي.
 */
class PlatformAuthorizationAttackMatrixTest extends TestCase
{
    use RefreshDatabase;

    private function organizationWithOwner(): array
    {
        $this->seed(MarketplaceCatalogSeeder::class);

        $owner = User::factory()->create(['is_platform_staff' => false]);
        $organization = Organization::create(['name' => 'مكتب أ', 'type' => 'firm', 'owner_id' => $owner->id]);
        Membership::create(['user_id' => $owner->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Owner]);

        $item = \App\Models\MarketplaceItem::where('key', 'marefa')->firstOrFail();
        $item->update(['billing_model' => 'both']);

        return [$owner, $organization, $item];
    }

    /** Attack #1 — Customer → /admin (أي مسار). */
    public function test_attack_1_customer_cannot_reach_admin_panel(): void
    {
        $customer = User::factory()->create(['is_platform_staff' => false]);

        $response = $this->actingAs($customer)->get('/admin');
        $response->assertForbidden();
    }

    /** Attack #2 — Customer → استدعاء OrganizationSubscriptionService مباشرة (تجاوز كامل لـFilament). */
    public function test_attack_2_customer_calling_subscription_service_directly_is_rejected(): void
    {
        [, $organization, $item] = $this->organizationWithOwner();
        $customer = User::factory()->create(['is_platform_staff' => false]);

        $this->expectException(AuthorizationException::class);
        app(OrganizationSubscriptionService::class)->create($customer, $organization, $item, 'Professional', 5);
    }

    /** Attack #3 — Member بمؤسسة A → فعل (تعديل عضوية) على مؤسسة B. */
    public function test_attack_3_member_of_organization_a_cannot_manage_members_of_organization_b(): void
    {
        [$ownerA, $orgA] = $this->organizationWithOwner();
        [$ownerB, $orgB] = $this->organizationWithOwner();

        $memberA = User::factory()->create(['is_platform_staff' => false]);
        Membership::create(['user_id' => $memberA->id, 'organization_id' => $orgA->id, 'role' => MembershipRole::Lawyer]);

        $targetMembershipInB = Membership::create(['user_id' => (User::factory()->create())->id, 'organization_id' => $orgB->id, 'role' => MembershipRole::Lawyer]);

        $this->expectException(AuthorizationException::class);
        app(MembershipService::class)->changeRole($memberA, $targetMembershipInB, MembershipRole::Admin);
    }

    /** Attack #4 — Admin بمؤسسة A → فعل (أرشفة) على مؤسسة B (Admin ليست Owner أصلًا، وليست بمؤسسة B). */
    public function test_attack_4_admin_of_organization_a_cannot_archive_organization_b(): void
    {
        [, $orgA] = $this->organizationWithOwner();
        [, $orgB] = $this->organizationWithOwner();

        $adminA = User::factory()->create(['is_platform_staff' => false]);
        Membership::create(['user_id' => $adminA->id, 'organization_id' => $orgA->id, 'role' => MembershipRole::Admin]);

        $this->expectException(AuthorizationException::class);
        app(OrganizationLifecycleService::class)->archive($adminA, $orgB);
    }

    /**
     * Attack #5 — Staff → مؤسسة بلا Owner حقيقي إطلاقًا. هذي بالضبط الحالة
     * اللي اكتُشفت بـPhase OL (admin@marefa.local بلا Membership) والتي
     * Option D صُمِّم لحلها. نبني هنا مؤسسة صناعية بصفر Membership من نوع
     * Owner (عضو Lawyer وحيد فقط) لإثبات الحل عمليًا — بلا لمس Org 1/Org 2
     * الحقيقيتين إطلاقًا.
     */
    public function test_attack_5_staff_can_manage_organization_with_no_real_owner(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $orphanOrg = Organization::create(['name' => 'مؤسسة بلا Owner فعلي', 'type' => 'firm', 'owner_id' => null]);
        $onlyMember = User::factory()->create();
        Membership::create(['user_id' => $onlyMember->id, 'organization_id' => $orphanOrg->id, 'role' => MembershipRole::Lawyer]);
        $this->assertSame(0, Membership::where('organization_id', $orphanOrg->id)->where('role', MembershipRole::Owner)->count());

        $staff = User::factory()->create(['is_platform_staff' => true]);

        app(OrganizationLifecycleService::class)->archive($staff, $orphanOrg);

        $this->assertSame('archived', $orphanOrg->fresh()->status);
        $this->assertFalse(
            Membership::where('user_id', $staff->id)->where('organization_id', $orphanOrg->id)->exists()
        );
    }

    /** Attack #6 — Staff → Hard Delete Organization. لا مسار له بالـDomain كليًا، حتى لـStaff. */
    public function test_attack_6_staff_has_no_hard_delete_path_at_all(): void
    {
        [, $organization] = $this->organizationWithOwner();
        $staff = User::factory()->create(['is_platform_staff' => true]);

        $this->assertFalse(method_exists(OrganizationLifecycleService::class, 'delete'));
        $this->assertFalse(method_exists(OrganizationLifecycleService::class, 'forceDelete'));

        $this->expectException(AuthorizationException::class);
        Gate::forUser($staff)->authorize('delete', $organization);
    }

    /**
     * Attack #7 — تلاعب مباشر (IDOR-style): استدعاء Policy مباشرة بمعرّف
     * مؤسسة مُزوَّر (Organization B) بمعزل تام عن أي Route/Filament — يثبت
     * أن Membership تُفحَص بـorganization_id الصريح، لا بوجود عضوية بأي مؤسسة.
     * (Route Model Binding نفسه سلوك إطاري قياسي لا يحتاج اختبارًا مخصَّصًا،
     * التركيز هنا على تحقق الـService/Policy الداخلي).
     */
    public function test_attack_7_forged_organization_id_is_rejected_by_policy_scoping(): void
    {
        [$ownerA, $orgA] = $this->organizationWithOwner();
        [, $orgB] = $this->organizationWithOwner();

        $this->assertTrue(Gate::forUser($ownerA)->allows('manageSubscription', $orgA));
        $this->assertFalse(Gate::forUser($ownerA)->allows('manageSubscription', $orgB));
    }

    /** Attack #8 — استدعاء Service مباشر بمعزل تام عن أي HTTP (لا Filament، لا Route). */
    public function test_attack_8_direct_service_invocation_outside_http_context_is_rejected(): void
    {
        [, $organization] = $this->organizationWithOwner();
        $customer = User::factory()->create(['is_platform_staff' => false]);
        $someMembership = Membership::query()->where('organization_id', $organization->id)->firstOrFail();

        $this->expectException(AuthorizationException::class);
        app(MembershipService::class)->changeRole($customer, $someMembership, MembershipRole::Admin);
    }

    /**
     * Attack #9 (إضافي) — Last Owner Rule قاعدة عمل، لا Authorization — كون
     * الفاعل Staff (مخوَّل بالكامل) لا يُلغي القاعدة الجوهرية لأنها ليست
     * فحص صلاحية بل ثابت Domain: لا مؤسسة بلا Owner واحد على الأقل.
     */
    public function test_attack_9_last_owner_rule_is_not_bypassed_even_by_staff(): void
    {
        [$owner, $organization] = $this->organizationWithOwner();
        $staff = User::factory()->create(['is_platform_staff' => true]);
        $ownerMembership = Membership::where('organization_id', $organization->id)->where('role', MembershipRole::Owner)->firstOrFail();

        $this->expectException(InvalidArgumentException::class);
        app(MembershipService::class)->remove($staff, $ownerMembership);
    }
}
