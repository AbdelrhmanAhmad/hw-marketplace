<?php

namespace Tests\Feature\Organization;

use App\Enums\AuditEvent;
use App\Enums\MembershipRole;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Final Execution Sprint — يغلق AD-016 بالكامل: تغيير Role، الإزالة، ونقل
 * الملكية أصبحت الثلاثة مُدقَّقة (كانت `add()`/الترقية لـOwner فقط مُدقَّقتين
 * سابقًا). راجع docs/final-execution-completion-report.md.
 */
class MembershipAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private function orgWithOwner(): array
    {
        $owner = User::factory()->create();
        $organization = Organization::create(['name' => 'مؤسسة اختبار Audit', 'type' => 'firm', 'owner_id' => $owner->id]);
        Membership::create(['user_id' => $owner->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Owner]);

        return [$owner, $organization];
    }

    public function test_role_change_is_audited(): void
    {
        [$owner, $organization] = $this->orgWithOwner();
        $target = User::factory()->create();
        $membership = Membership::create(['user_id' => $target->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);

        app(MembershipService::class)->changeRole($owner, $membership, MembershipRole::Admin);

        $this->assertTrue(
            AuditLog::where('event', AuditEvent::MembershipRoleChanged->value)
                ->where('subject_id', $membership->id)
                ->where('organization_id', $organization->id)
                ->where('actor_user_id', $owner->id)
                ->exists()
        );
    }

    public function test_removal_is_audited(): void
    {
        [$owner, $organization] = $this->orgWithOwner();
        $target = User::factory()->create();
        $membership = Membership::create(['user_id' => $target->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);
        $membershipId = $membership->id;

        app(MembershipService::class)->remove($owner, $membership);

        $this->assertTrue(
            AuditLog::where('event', AuditEvent::MembershipRemoved->value)
                ->where('subject_id', $membershipId)
                ->exists()
        );
    }

    public function test_ownership_transfer_is_audited(): void
    {
        [$owner, $organization] = $this->orgWithOwner();
        $ownerMembership = Membership::where('user_id', $owner->id)->first();
        $target = User::factory()->create();
        $targetMembership = Membership::create(['user_id' => $target->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);

        app(MembershipService::class)->transferOwnership($owner, $ownerMembership, $targetMembership);

        $this->assertTrue(
            AuditLog::where('event', AuditEvent::OwnershipTransferred->value)
                ->where('subject_id', $targetMembership->id)
                ->exists()
        );
    }
}
