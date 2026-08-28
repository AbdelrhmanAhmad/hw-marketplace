<?php

namespace Tests\Feature\Platform;

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Platform Authorization Foundation — Goal 1: إغلاق /admin فعليًا عبر
 * canAccessPanel()، لا إخفاء واجهة. راجع
 * docs/platform-authorization-foundation-specification.md §1، §7 (Attack #1).
 */
class PlatformStaffAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_panel(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect();
        $this->assertStringContainsString('/admin/login', $response->headers->get('Location'));
    }

    public function test_customer_with_no_membership_and_no_staff_flag_is_rejected_by_backend(): void
    {
        $customer = User::factory()->create(['is_platform_staff' => false]);

        $response = $this->actingAs($customer)->get('/admin');

        $response->assertForbidden();
    }

    /**
     * الحالة الحرجة: كون المستخدم Owner حقيقي بمؤسسة لا يمنحه دخولًا لـ/admin
     * بمفرده — الدخول مشروط بـPlatform Staff حصرًا (قسم 4.1 بالمواصفة).
     */
    public function test_organization_owner_without_staff_flag_is_rejected_by_backend(): void
    {
        $owner = User::factory()->create(['is_platform_staff' => false]);
        $organization = Organization::create(['name' => 'مكتب الاختبار', 'type' => 'firm', 'owner_id' => $owner->id]);
        Membership::create(['user_id' => $owner->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Owner]);

        $response = $this->actingAs($owner)->get('/admin');

        $response->assertForbidden();
    }

    public function test_platform_staff_is_allowed_by_backend(): void
    {
        $staff = User::factory()->create(['is_platform_staff' => true]);

        $response = $this->actingAs($staff)->get('/admin');

        $response->assertOk();
    }
}
