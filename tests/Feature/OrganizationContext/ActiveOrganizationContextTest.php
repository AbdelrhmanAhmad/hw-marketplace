<?php

namespace Tests\Feature\OrganizationContext;

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Support\ActiveOrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActiveOrganizationContextTest extends TestCase
{
    use RefreshDatabase;

    private function membership(User $user, string $orgName, MembershipRole $role = MembershipRole::Owner): Membership
    {
        $organization = Organization::create(['name' => $orgName, 'type' => 'firm', 'owner_id' => $user->id]);

        return Membership::create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'role' => $role,
        ]);
    }

    public function test_switching_to_a_member_organization_updates_context(): void
    {
        $user = User::factory()->create();
        $membership = $this->membership($user, 'مكتب أ');

        $this->actingAs($user)
            ->post(route('organization-context.switch', $membership->organization))
            ->assertRedirect();

        $this->assertSame($membership->organization_id, session('active_organization_id'));
    }

    public function test_switching_to_a_non_member_organization_is_forbidden(): void
    {
        $user = User::factory()->create();
        $otherOwner = User::factory()->create();
        $foreignOrg = Organization::create(['name' => 'مكتب غريب', 'type' => 'firm', 'owner_id' => $otherOwner->id]);

        $response = $this->actingAs($user)->post(route('organization-context.switch', $foreignOrg));

        $response->assertForbidden();
        $this->assertNull(session('active_organization_id'));
    }

    public function test_switching_to_personal_clears_context(): void
    {
        $user = User::factory()->create();
        $membership = $this->membership($user, 'مكتب أ');

        $this->actingAs($user)->post(route('organization-context.switch', $membership->organization));
        $this->actingAs($user)->post(route('organization-context.personal'));

        $this->assertNull(session('active_organization_id'));
    }

    public function test_user_with_two_organizations_switches_between_them_without_mixing(): void
    {
        $user = User::factory()->create();
        $membershipA = $this->membership($user, 'مكتب أ', MembershipRole::Partner);
        $membershipB = $this->membership($user, 'مكتب ب', MembershipRole::Lawyer);

        $this->actingAs($user)->post(route('organization-context.switch', $membershipA->organization));
        $this->assertSame($membershipA->organization_id, session('active_organization_id'));

        $this->actingAs($user)->post(route('organization-context.switch', $membershipB->organization));
        $this->assertSame($membershipB->organization_id, session('active_organization_id'));
        $this->assertNotSame($membershipA->organization_id, session('active_organization_id'));
    }

    public function test_stale_session_is_corrected_automatically_after_membership_removed(): void
    {
        $user = User::factory()->create();
        $membership = $this->membership($user, 'مكتب أ');

        $this->actingAs($user)->post(route('organization-context.switch', $membership->organization));
        $this->assertSame($membership->organization_id, session('active_organization_id'));

        $membership->delete();

        // أي طلب لاحق يمر بالـMiddleware يصحّح السياق تلقائيًا (BR-2A-02).
        $this->actingAs($user)->get('/my/apps');

        $this->assertNull(session('active_organization_id'));
    }

    public function test_current_returns_null_for_organization_user_is_not_a_member_of_even_if_session_tampered(): void
    {
        $user = User::factory()->create();
        $otherOwner = User::factory()->create();
        $foreignOrg = Organization::create(['name' => 'مكتب غريب', 'type' => 'firm', 'owner_id' => $otherOwner->id]);

        $this->actingAs($user);
        session(['active_organization_id' => $foreignOrg->id]);

        $this->assertNull(ActiveOrganizationContext::current());
    }

    public function test_switcher_does_not_appear_for_user_without_organizations(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/my/apps');

        $response->assertOk();
        $response->assertDontSee('organization-context.switch');
    }

    public function test_switcher_appears_and_reflects_active_organization(): void
    {
        $user = User::factory()->create();
        $membership = $this->membership($user, 'مكتب الاختبار');

        $this->actingAs($user)->post(route('organization-context.switch', $membership->organization));

        $response = $this->actingAs($user)->get('/my/apps');

        $response->assertOk();
        $response->assertSee('مكتب الاختبار');
    }
}
