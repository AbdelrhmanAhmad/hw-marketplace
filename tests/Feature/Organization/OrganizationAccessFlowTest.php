<?php

namespace Tests\Feature\Organization;

use App\Enums\MembershipRole;
use App\Models\MarketplaceItem;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationSubscriptionService;
use App\Services\SeatService;
use App\Services\SubscriptionService;
use Database\Seeders\MarketplaceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * الحالات الثلاث المطلوبة صراحة: Organization isolation، Multi-organization،
 * Personal + Organization. راجع docs/phase-2b-organization-subscription-access-design.md قسم D.
 */
class OrganizationAccessFlowTest extends TestCase
{
    use RefreshDatabase;

    private function setupOrgWithSeat(string $orgName, User $member, MembershipRole $role = MembershipRole::Lawyer, int $seatLimit = 5): array
    {
        $owner = User::factory()->create();
        $organization = Organization::create(['name' => $orgName, 'type' => 'firm', 'owner_id' => $owner->id]);
        Membership::create(['user_id' => $owner->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Owner]);
        Membership::create(['user_id' => $member->id, 'organization_id' => $organization->id, 'role' => $role]);

        $item = MarketplaceItem::where('key', 'marefa')->firstOrFail();
        $item->update(['billing_model' => 'both']);

        $subscription = app(OrganizationSubscriptionService::class)->create($owner, $organization, $item, 'Professional', $seatLimit);
        app(SeatService::class)->assign($owner, $subscription, $member);

        return [$owner, $organization, $subscription];
    }

    // ── Organization Isolation ──────────────────────────────────────────

    public function test_seat_management_page_rejects_user_with_no_membership_in_target_organization(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $attacker = User::factory()->create();
        [$owner, $organization] = $this->setupOrgWithSeat('مكتب ب', User::factory()->create());

        $response = $this->actingAs($attacker)->get(route('organization-seats.index', $organization));

        $response->assertForbidden();
    }

    public function test_seat_assign_endpoint_rejects_direct_post_from_non_member(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $attacker = User::factory()->create();
        $victim = User::factory()->create();
        [$owner, $organization, $subscription] = $this->setupOrgWithSeat('مكتب ب', $victim);

        $response = $this->actingAs($attacker)->post(
            route('organization-seats.assign', [$organization, $subscription, $victim])
        );

        $response->assertForbidden();
    }

    public function test_member_of_org_a_cannot_manage_seats_of_org_b_via_url_id_manipulation(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $memberA = User::factory()->create();
        [$ownerA, $orgA] = $this->setupOrgWithSeat('مكتب أ', $memberA, MembershipRole::Owner);
        [$ownerB, $orgB, $subscriptionB] = $this->setupOrgWithSeat('مكتب ب', User::factory()->create());

        // memberA مالك بمؤسسته (A) لكن ليس عضوًا إطلاقًا بـB — يحاول الوصول لموارد B مباشرة بمعرّفاتها.
        $response = $this->actingAs($memberA)->get(route('organization-seats.index', $orgB));
        $response->assertForbidden();

        $response = $this->actingAs($memberA)->post(
            route('organization-seats.assign', [$orgB, $subscriptionB, $memberA])
        );
        $response->assertForbidden();
    }

    public function test_admin_cannot_create_subscription_only_owner_can(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $admin = User::factory()->create();
        $owner = User::factory()->create();
        $organization = Organization::create(['name' => 'مكتب الاختبار', 'type' => 'firm', 'owner_id' => $owner->id]);
        Membership::create(['user_id' => $admin->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Admin]);

        $this->assertFalse($admin->can('manageSubscription', $organization));
        $this->assertTrue($admin->can('manageSeats', $organization));
    }

    public function test_tampering_active_organization_context_session_does_not_bypass_membership_check(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $attacker = User::factory()->create();
        [$owner, $organization] = $this->setupOrgWithSeat('مكتب ب', User::factory()->create());

        // AD-012 — تزوير الجلسة لا يجب يمنح شيئًا؛ الفحص الحقيقي بالـController/Policy يعيد التحقق من Membership.
        session(['active_organization_id' => $organization->id]);

        $response = $this->actingAs($attacker)->get(route('organization-seats.index', $organization));

        $response->assertForbidden();
    }

    // ── Multi-Organization (لا اختلاط) ──────────────────────────────────

    public function test_access_from_organization_a_does_not_leak_into_organization_b_context(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $user = User::factory()->create();

        [$ownerA, $orgA] = $this->setupOrgWithSeat('مكتب أ', $user);
        $orgB = Organization::create(['name' => 'مكتب ب', 'type' => 'firm', 'owner_id' => User::factory()->create()->id]);
        Membership::create(['user_id' => $user->id, 'organization_id' => $orgB->id, 'role' => MembershipRole::Lawyer]);
        // ملاحظة: user له مقعد فعّال بـA، ولا اشتراك مؤسسي إطلاقًا بـB لنفس التطبيق.

        $this->actingAs($user)->post(route('organization-context.switch', $orgB));

        $response = $this->actingAs($user)->get('/my/apps');

        $response->assertOk();
        // بسياق B، بوابة معرفة يجب ألا تظهر كوصول "من B" — لا اشتراك مؤسسي لـB أصلًا.
        // (لو ظهرت، ستكون فقط عبر مصدر "شخصي" المستقل — لا عبر B إطلاقًا).
        $response->assertDontSee('اشتراك مكتب ب');
    }

    public function test_switching_between_two_organizations_shows_correct_isolated_access(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $user = User::factory()->create();

        [$ownerA, $orgA, ] = $this->setupOrgWithSeat('مكتب أ', $user);

        $this->actingAs($user)->post(route('organization-context.switch', $orgA));
        $responseA = $this->actingAs($user)->get('/my/apps');
        $responseA->assertSee('اشتراك مكتب أ');

        // مؤسسة ثانية بلا أي اشتراك مؤسسي إطلاقًا
        $orgB = Organization::create(['name' => 'مكتب ج', 'type' => 'firm', 'owner_id' => User::factory()->create()->id]);
        Membership::create(['user_id' => $user->id, 'organization_id' => $orgB->id, 'role' => MembershipRole::Lawyer]);

        $this->actingAs($user)->post(route('organization-context.switch', $orgB));
        $responseB = $this->actingAs($user)->get('/my/apps');
        $responseB->assertDontSee('اشتراك مكتب أ');
    }

    // ── Personal + Organization (استقلال تام) ───────────────────────────

    public function test_cancelling_personal_subscription_does_not_affect_organization_access(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $user = User::factory()->create();
        [$owner, $organization, $subscription] = $this->setupOrgWithSeat('مكتب أ', $user);

        // نفس المستخدم يشترك شخصيًا بنفس التطبيق أيضًا
        $item = MarketplaceItem::where('key', 'marefa')->firstOrFail();
        app(SubscriptionService::class)->subscribeUserToFreeItem($user, $item);

        // يلغي اشتراكه الشخصي
        $this->actingAs($user)->post(route('platform.marketplace.cancel', 'marefa'));

        // وصوله عبر المؤسسة يبقى سليمًا تمامًا
        $this->assertTrue($subscription->accessAssignments()->where('user_id', $user->id)->active()->exists());
    }

    public function test_organization_subscription_cancellation_does_not_affect_personal_subscription(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $user = User::factory()->create();
        [$owner, $organization, $subscription] = $this->setupOrgWithSeat('مكتب أ', $user);

        $item = MarketplaceItem::where('key', 'marefa')->firstOrFail();
        app(SubscriptionService::class)->subscribeUserToFreeItem($user, $item);

        app(OrganizationSubscriptionService::class)->cancel($owner, $subscription);

        $this->assertTrue($user->hasActiveSubscription('marefa') || $user->marketplaceSubscriptions()->where('marketplace_item_id', $item->id)->active()->exists());
    }
}
