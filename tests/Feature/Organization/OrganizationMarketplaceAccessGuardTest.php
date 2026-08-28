<?php

namespace Tests\Feature\Organization;

use App\Enums\MembershipRole;
use App\Filament\Resources\OrganizationResource\Pages\EditOrganization;
use App\Filament\Resources\OrganizationResource\RelationManagers\SubscriptionsRelationManager;
use App\Models\MarketplaceItem;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\OrganizationLifecycleService;
use App\Services\OrganizationSubscriptionService;
use App\Services\SeatService;
use Database\Seeders\MarketplaceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * AD-018 — Archived Organization Cannot Receive New or Expanded Marketplace
 * Access. راجع docs/organization-lifecycle-domain-state-design.md وقسم
 * Attack Matrix. يغطي كل سيناريو من الجدول (11 سيناريو) + قرار السماح
 * الصريح لعمليات Membership (Membership ≠ Marketplace Access، AD-007/AD-018).
 */
class OrganizationMarketplaceAccessGuardTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Organization, 2: MarketplaceItem} */
    private function activeOrgWithOwner(): array
    {
        $this->seed(MarketplaceCatalogSeeder::class);

        $owner = User::factory()->create(['is_platform_staff' => false]);
        $organization = Organization::create(['name' => 'مؤسسة اختبار AD-018', 'type' => 'firm', 'owner_id' => $owner->id]);
        Membership::create(['user_id' => $owner->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Owner]);

        $item = MarketplaceItem::where('key', 'marefa')->firstOrFail();
        $item->update(['billing_model' => 'both']);

        return [$owner, $organization, $item];
    }

    // --- Attack #4/#1 — Owner/Staff مخوَّلان Authorization-wise، State Guard يرفض رغم ذلك ---

    public function test_owner_cannot_create_subscription_for_own_archived_organization(): void
    {
        [$owner, $organization, $item] = $this->activeOrgWithOwner();
        app(OrganizationLifecycleService::class)->archive($owner, $organization);

        $this->expectException(InvalidArgumentException::class);
        app(OrganizationSubscriptionService::class)->create($owner, $organization->fresh(), $item, 'Professional', 5);
    }

    public function test_platform_staff_cannot_create_subscription_for_archived_organization_despite_full_authorization(): void
    {
        [$owner, $organization, $item] = $this->activeOrgWithOwner();
        app(OrganizationLifecycleService::class)->archive($owner, $organization);
        $staff = User::factory()->create(['is_platform_staff' => true]);

        $this->expectException(InvalidArgumentException::class);
        app(OrganizationSubscriptionService::class)->create($staff, $organization->fresh(), $item, 'Professional', 5);
    }

    /**
     * إصلاح Race Condition (AD018-1) — يثبت أن create() يعيد قراءة حالة
     * المؤسسة من الصف المقفول طازجًا (لا يثق بالـinstance المُمرَّر). هذا
     * يثبت **ترتيب القفل وسلوك المعاملة** فقط — لا Concurrency حقيقي (غير
     * قابل للإثبات بمحرك SQLite Single-Writer المُستخدَم هنا؛ Concurrency
     * الفعلي يتطلب محرك Row-Level Locking حقيقي كـMySQL/Postgres، راجع
     * docs/ad-018-race-condition-fix-completion-report.md).
     */
    public function test_create_rechecks_archived_status_on_locked_row_even_with_a_stale_organization_instance(): void
    {
        [$owner, $organization, $item] = $this->activeOrgWithOwner();

        // Instance منفصلة، تمثّل "ما رآه الفاعل قبل لحظة الأرشفة" — تبقى
        // 'active' بالذاكرة حتى لو تغيّرت قاعدة البيانات فعليًا بعدها.
        $staleOrganization = Organization::find($organization->id);

        app(OrganizationLifecycleService::class)->archive($owner, Organization::find($organization->id));

        $this->assertSame('active', $staleOrganization->status, 'تأكيد إن الـinstance فعلًا قديمة بالذاكرة.');

        $this->expectException(InvalidArgumentException::class);
        app(OrganizationSubscriptionService::class)->create($owner, $staleOrganization, $item, 'Professional', 5);
    }

    // --- Attack #6 — رفع حد المقاعد مرفوض على مؤسسة مؤرشَفة ---

    public function test_staff_cannot_increase_seat_limit_on_archived_organization(): void
    {
        [$owner, $organization, $item] = $this->activeOrgWithOwner();
        $subscription = app(OrganizationSubscriptionService::class)->create($owner, $organization, $item, 'Professional', 5);
        app(OrganizationLifecycleService::class)->archive($owner, $organization);
        $staff = User::factory()->create(['is_platform_staff' => true]);

        $this->expectException(InvalidArgumentException::class);
        app(OrganizationSubscriptionService::class)->changeSeatLimit($staff, $subscription->fresh(), 10);
    }

    /** تخفيض الحد يبقى مسموحًا دائمًا — لا سبب أمني للمنع. */
    public function test_decreasing_seat_limit_on_archived_organization_still_works(): void
    {
        [$owner, $organization, $item] = $this->activeOrgWithOwner();
        $subscription = app(OrganizationSubscriptionService::class)->create($owner, $organization, $item, 'Professional', 5);
        app(OrganizationLifecycleService::class)->archive($owner, $organization);

        app(OrganizationSubscriptionService::class)->changeSeatLimit($owner, $subscription->fresh(), 2);

        $this->assertSame(2, $subscription->fresh()->plan->seat_limit);
    }

    // --- Attack #5 — تعيين مقعد مرفوض على مؤسسة مؤرشَفة ---

    public function test_staff_cannot_assign_seat_on_archived_organization(): void
    {
        [$owner, $organization, $item] = $this->activeOrgWithOwner();
        $subscription = app(OrganizationSubscriptionService::class)->create($owner, $organization, $item, 'Professional', 5);
        $target = User::factory()->create();
        Membership::create(['user_id' => $target->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);

        app(OrganizationLifecycleService::class)->archive($owner, $organization);
        $staff = User::factory()->create(['is_platform_staff' => true]);

        $this->expectException(InvalidArgumentException::class);
        app(SeatService::class)->assign($staff, $subscription->fresh(), $target);
    }

    // --- release/cancel/revoke تبقى مسموحة دائمًا (تُنقِص فقط) ---

    public function test_releasing_a_seat_on_archived_organization_still_works(): void
    {
        [$owner, $organization, $item] = $this->activeOrgWithOwner();
        $subscription = app(OrganizationSubscriptionService::class)->create($owner, $organization, $item, 'Professional', 5);
        $target = User::factory()->create();
        Membership::create(['user_id' => $target->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);
        $seat = app(SeatService::class)->assign($owner, $subscription, $target);

        app(OrganizationLifecycleService::class)->archive($owner, $organization);

        app(SeatService::class)->release($owner, $seat);
        $this->assertSame('released', $seat->fresh()->status);
    }

    // --- Attack #11 — Restore يعيد إمكانية الوصول الجديد ---

    public function test_restore_reopens_ability_to_create_new_subscription(): void
    {
        [$owner, $organization, $item] = $this->activeOrgWithOwner();
        app(OrganizationLifecycleService::class)->archive($owner, $organization);
        app(OrganizationLifecycleService::class)->restore($owner, $organization);

        $subscription = app(OrganizationSubscriptionService::class)->create($owner, $organization->fresh(), $item, 'Professional', 5);

        $this->assertSame('active', $subscription->status);
    }

    /** يؤكد صراحة: Restore لا يعيد أي Subscription/Access سابق تلقائيًا (AD-014). */
    public function test_restore_does_not_auto_reactivate_previously_cancelled_subscription(): void
    {
        [$owner, $organization, $item] = $this->activeOrgWithOwner();
        $subscription = app(OrganizationSubscriptionService::class)->create($owner, $organization, $item, 'Professional', 5);
        app(OrganizationLifecycleService::class)->archive($owner, $organization);
        $this->assertSame('cancelled', $subscription->fresh()->status);

        app(OrganizationLifecycleService::class)->restore($owner, $organization);

        $this->assertSame('cancelled', $subscription->fresh()->status);
    }

    // --- Membership operations مسموحة على مؤسسة مؤرشَفة (قرار صريح: Membership ≠ Marketplace Access) ---

    public function test_staff_can_add_member_to_archived_organization(): void
    {
        [$owner, $organization] = $this->activeOrgWithOwner();
        app(OrganizationLifecycleService::class)->archive($owner, $organization);
        $staff = User::factory()->create(['is_platform_staff' => true]);
        $target = User::factory()->create();

        $membership = app(MembershipService::class)->add($staff, $organization, $target, MembershipRole::Admin);

        $this->assertSame(MembershipRole::Admin, $membership->role);
    }

    public function test_owner_can_change_member_role_on_archived_organization(): void
    {
        [$owner, $organization] = $this->activeOrgWithOwner();
        $target = User::factory()->create();
        $membership = Membership::create(['user_id' => $target->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);
        app(OrganizationLifecycleService::class)->archive($owner, $organization);

        app(MembershipService::class)->changeRole($owner, $membership, MembershipRole::Admin);

        $this->assertSame(MembershipRole::Admin, $membership->fresh()->role);
    }

    /** Transfer Ownership على مؤسسة مؤرشَفة يبقى محكومًا بقواعد AD-017 فقط — بلا قيد إضافي من Archive. */
    public function test_real_owner_can_still_transfer_ownership_on_archived_organization(): void
    {
        [$owner, $organization] = $this->activeOrgWithOwner();
        $ownerMembership = Membership::where('user_id', $owner->id)->where('organization_id', $organization->id)->first();
        $target = User::factory()->create();
        $targetMembership = Membership::create(['user_id' => $target->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);

        app(OrganizationLifecycleService::class)->archive($owner, $organization);

        app(MembershipService::class)->transferOwnership($owner, $ownerMembership, $targetMembership);

        $this->assertSame(MembershipRole::Owner, $targetMembership->fresh()->role);
    }

    // --- Attack #8 — نفس القيد عبر Livewire/Filament، لا بايباس عبر الواجهة ---

    public function test_livewire_create_subscription_action_is_rejected_on_archived_organization(): void
    {
        [$owner, $organization, $item] = $this->activeOrgWithOwner();
        app(OrganizationLifecycleService::class)->archive($owner, $organization);
        $staff = User::factory()->create(['is_platform_staff' => true]);

        $countBefore = $organization->marketplaceSubscriptions()->count();

        Livewire::actingAs($staff)
            ->test(SubscriptionsRelationManager::class, [
                'ownerRecord' => $organization,
                'pageClass' => EditOrganization::class,
            ])
            ->callTableAction('create', data: [
                'marketplace_item_id' => $item->id,
                'plan_name' => 'Professional',
                'seat_limit' => 5,
            ]);

        $this->assertSame($countBefore, $organization->marketplaceSubscriptions()->count());
    }

    // --- Attack #10 — Active Organization Context لا علاقة له إطلاقًا ---

    public function test_guard_does_not_consult_session_or_active_organization_context(): void
    {
        [$owner, $organization, $item] = $this->activeOrgWithOwner();
        app(OrganizationLifecycleService::class)->archive($owner, $organization);

        // لا جلسة، لا Auth::user() مضبوط عالميًا — الاستدعاء المباشر وحده يحدد كل شيء.
        $this->assertGuest();

        $this->expectException(InvalidArgumentException::class);
        app(OrganizationSubscriptionService::class)->create($owner, $organization->fresh(), $item, 'Professional', 5);
    }
}
