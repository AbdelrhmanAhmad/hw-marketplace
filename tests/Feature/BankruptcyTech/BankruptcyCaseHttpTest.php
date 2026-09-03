<?php

namespace Tests\Feature\BankruptcyTech;

use App\Enums\MembershipRole;
use App\Models\MarketplaceItem;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Services\BankruptcyCaseService;
use App\Services\OrganizationSubscriptionService;
use App\Services\SubscriptionService;
use Database\Seeders\MarketplaceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Final Execution Sprint — إفلاس تك عبر HTTP كامل: Entitlement Gate،
 * IDOR/Tenant Isolation عبر طلبات حقيقية، لا استدعاء Service مباشر فقط.
 */
class BankruptcyCaseHttpTest extends TestCase
{
    use RefreshDatabase;

    private function activateBankruptcyTechFor(User $user): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $item = MarketplaceItem::where('key', 'bankruptcy-tech')->firstOrFail();
        app(SubscriptionService::class)->subscribeUserToFreeItem($user, $item);
    }

    // --- Entitlement Gate ---

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/apps/bankruptcy-tech')->assertRedirect('/login');
    }

    public function test_authenticated_but_not_entitled_user_is_rejected(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/apps/bankruptcy-tech')->assertForbidden();
    }

    public function test_entitled_user_can_access_the_app(): void
    {
        $user = User::factory()->create();
        $this->activateBankruptcyTechFor($user);

        $this->actingAs($user)->get('/apps/bankruptcy-tech')->assertOk();
    }

    // --- المسار الشخصي الكامل عبر HTTP حقيقي ---

    public function test_full_personal_case_flow_via_real_http_requests(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $this->activateBankruptcyTechFor($user);

        $this->actingAs($user)
            ->post('/apps/bankruptcy-tech/cases', ['title' => 'قضية HTTP', 'description' => 'وصف'])
            ->assertRedirect();

        $case = \App\Models\BankruptcyCase::where('title', 'قضية HTTP')->firstOrFail();

        $this->actingAs($user)->get("/apps/bankruptcy-tech/cases/{$case->id}")->assertOk()->assertSee('قضية HTTP');

        $this->actingAs($user)->post("/apps/bankruptcy-tech/cases/{$case->id}/parties", [
            'name' => 'شركة تجريبية', 'role' => 'debtor',
        ])->assertRedirect();

        $this->actingAs($user)->post("/apps/bankruptcy-tech/cases/{$case->id}/procedures", [
            'title' => 'إجراء أول',
        ])->assertRedirect();

        $this->actingAs($user)->post("/apps/bankruptcy-tech/cases/{$case->id}/notes", [
            'body' => 'ملاحظة عبر HTTP',
        ])->assertRedirect();

        $file = UploadedFile::fake()->create('مستند.pdf', 50);
        $this->actingAs($user)->post("/apps/bankruptcy-tech/cases/{$case->id}/documents", [
            'title' => 'مستند تجريبي', 'file' => $file,
        ])->assertRedirect();

        $this->assertSame(1, $case->fresh()->parties()->count());
        $this->assertSame(1, $case->fresh()->procedures()->count());
        $this->assertSame(1, $case->fresh()->notes()->count());
        $this->assertSame(1, $case->fresh()->documents()->count());

        // المرحلة 1 — النموذج القانوني الكامل، عبر HTTP حقيقي.
        $this->actingAs($user)->post("/apps/bankruptcy-tech/cases/{$case->id}/creditors", [
            'name' => 'دائن HTTP', 'amount' => 5000, 'priority' => 'p3_unsecured',
        ])->assertRedirect();

        $this->actingAs($user)->post("/apps/bankruptcy-tech/cases/{$case->id}/assets", [
            'name' => 'أصل HTTP', 'value' => 2000, 'location' => 'الرياض',
        ])->assertRedirect();

        $this->actingAs($user)->post("/apps/bankruptcy-tech/cases/{$case->id}/employees", [
            'name' => 'موظف HTTP', 'salary' => 6000, 'join_date' => now()->subYears(2)->toDateString(),
        ])->assertRedirect();

        $this->actingAs($user)->patch("/apps/bankruptcy-tech/cases/{$case->id}/wizard", [
            'insolvency_status' => 'actual', 'is_active' => 'yes',
        ])->assertRedirect();

        $event = $case->fresh()->timelineEvents()->first();
        $this->actingAs($user)->patch("/apps/bankruptcy-tech/cases/{$case->id}/timeline/{$event->id}/toggle")->assertRedirect();

        $this->assertSame(1, $case->fresh()->creditors()->count());
        $this->assertSame(1, $case->fresh()->assets()->count());
        $this->assertSame(1, $case->fresh()->employees()->count());
        $this->assertSame('actual', $case->fresh()->insolvency_status);
        $this->assertTrue($event->fresh()->done);
    }

    // --- IDOR / Tenant Isolation عبر HTTP حقيقي ---

    public function test_user_cannot_view_another_users_personal_case_via_direct_url(): void
    {
        $owner = User::factory()->create();
        $this->activateBankruptcyTechFor($owner);
        $case = app(BankruptcyCaseService::class)->createCase($owner, null, 'قضية خاصة');

        $attacker = User::factory()->create();
        $this->activateBankruptcyTechFor($attacker);

        $this->actingAs($attacker)->get("/apps/bankruptcy-tech/cases/{$case->id}")->assertForbidden();
    }

    /**
     * حاسم: $ownerB يحصل على Seat/AccessAssignment حقيقي بمؤسسته (يجتاز
     * حاجز Entitlement فعليًا، لا يُرفَض بسببه) — بهذا الشكل فقط الرفض
     * اللاحق يثبت عزل BankruptcyCasePolicy تحديدًا، لا مجرد غياب اشتراك.
     */
    public function test_member_of_organization_a_cannot_view_organization_b_case_via_direct_url(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $item = MarketplaceItem::where('key', 'bankruptcy-tech')->firstOrFail();

        $ownerA = User::factory()->create();
        $orgA = Organization::create(['name' => 'مؤسسة أ', 'type' => 'firm', 'owner_id' => $ownerA->id]);
        Membership::create(['user_id' => $ownerA->id, 'organization_id' => $orgA->id, 'role' => MembershipRole::Owner]);
        app(OrganizationSubscriptionService::class)->create($ownerA, $orgA, $item, 'Professional', 5);
        $caseA = app(BankruptcyCaseService::class)->createCase($ownerA, $orgA, 'قضية مؤسسة أ');

        $ownerB = User::factory()->create();
        $orgB = Organization::create(['name' => 'مؤسسة ب', 'type' => 'firm', 'owner_id' => $ownerB->id]);
        Membership::create(['user_id' => $ownerB->id, 'organization_id' => $orgB->id, 'role' => MembershipRole::Owner]);
        $subscriptionB = app(OrganizationSubscriptionService::class)->create($ownerB, $orgB, $item, 'Professional', 5);
        app(\App\Services\SeatService::class)->assign($ownerB, $subscriptionB, $ownerB);

        // إثبات إيجابي أولًا: $ownerB مُخوَّل فعليًا بمؤسسته الخاصة (لا يُرفَض
        // لغياب Entitlement — نافي الاحتمال الخاطئ قبل اختبار العزل).
        $caseB = app(BankruptcyCaseService::class)->createCase($ownerB, $orgB, 'قضية مؤسسة ب');
        $this->actingAs($ownerB)
            ->withSession(['active_organization_id' => $orgB->id])
            ->get("/apps/bankruptcy-tech/cases/{$caseB->id}")
            ->assertOk();

        // العزل الفعلي: نفس المستخدم، نفس الجلسة المُخوَّلة، قضية مؤسسة أخرى.
        $this->actingAs($ownerB)
            ->withSession(['active_organization_id' => $orgB->id])
            ->get("/apps/bankruptcy-tech/cases/{$caseA->id}")
            ->assertForbidden();
    }

    /** إجراء تابع لقضية أخرى، مُمرَّر عبر رابط قضية يملكها المهاجم فعليًا — لا يجوز يؤثر. */
    public function test_procedure_belonging_to_a_different_case_cannot_be_manipulated_via_wrong_case_url(): void
    {
        $userA = User::factory()->create();
        $this->activateBankruptcyTechFor($userA);
        $caseA = app(BankruptcyCaseService::class)->createCase($userA, null, 'قضية أ');
        $procedureA = app(BankruptcyCaseService::class)->addProcedure($userA, $caseA, ['title' => 'إجراء أ']);

        $userB = User::factory()->create();
        $this->activateBankruptcyTechFor($userB);
        $caseB = app(BankruptcyCaseService::class)->createCase($userB, null, 'قضية ب');

        // المهاجم يملك caseB فعليًا، لكن يحاول تعديل إجراء يخص caseA عبر تركيب رابط بمعرّف caseB.
        $this->actingAs($userB)
            ->patch("/apps/bankruptcy-tech/cases/{$caseB->id}/procedures/{$procedureA->id}/status", ['status' => 'completed'])
            ->assertNotFound();

        $this->assertSame('pending', $procedureA->fresh()->status);
    }

    /** حدث جدول زمني تابع لقضية أخرى، مُمرَّر عبر رابط قضية يملكها المهاجم فعليًا — لا يجوز يؤثر. */
    public function test_timeline_event_belonging_to_a_different_case_cannot_be_toggled_via_wrong_case_url(): void
    {
        $userA = User::factory()->create();
        $this->activateBankruptcyTechFor($userA);
        $caseA = app(BankruptcyCaseService::class)->createCase($userA, null, 'قضية أ');
        $eventA = $caseA->timelineEvents()->first();

        $userB = User::factory()->create();
        $this->activateBankruptcyTechFor($userB);
        $caseB = app(BankruptcyCaseService::class)->createCase($userB, null, 'قضية ب');

        $this->actingAs($userB)
            ->patch("/apps/bankruptcy-tech/cases/{$caseB->id}/timeline/{$eventA->id}/toggle")
            ->assertNotFound();

        $this->assertFalse($eventA->fresh()->done);
    }

    public function test_document_download_requires_case_access(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $this->activateBankruptcyTechFor($owner);
        $case = app(BankruptcyCaseService::class)->createCase($owner, null, 'قضية بمستند');
        $document = app(BankruptcyCaseService::class)->uploadDocument($owner, $case, UploadedFile::fake()->create('سري.pdf', 50), 'مستند سري');

        $attacker = User::factory()->create();
        $this->activateBankruptcyTechFor($attacker);

        $this->actingAs($attacker)
            ->get("/apps/bankruptcy-tech/cases/{$case->id}/documents/{$document->id}/download")
            ->assertForbidden();

        $this->actingAs($owner)
            ->get("/apps/bankruptcy-tech/cases/{$case->id}/documents/{$document->id}/download")
            ->assertOk();
    }

    // --- إصلاحات ملاحظات المستخدم ---

    /** حرج: القضية الجديدة تفتح مباشرة على معالج التشخيص، لا "نظرة عامة" فارغة. */
    public function test_creating_a_case_redirects_straight_to_the_wizard_tab(): void
    {
        $user = User::factory()->create();
        $this->activateBankruptcyTechFor($user);

        $response = $this->actingAs($user)->post('/apps/bankruptcy-tech/cases', ['title' => 'قضية جديدة']);

        $response->assertRedirect();
        $this->assertStringEndsWith('#wizard', $response->headers->get('Location'));
    }

    /**
     * حرج — هذا الاختبار يقفل الخلل الأكبر اللي أبلغ عنه المستخدم: أي حفظ
     * (بمعالج التشخيص أو غيره) كان يُعيد المستخدم دائمًا لتبويب "نظرة عامة"
     * بدل التبويب اللي كان يعمل فيه فعليًا.
     */
    public function test_saving_wizard_answers_redirects_back_to_the_wizard_tab_not_overview(): void
    {
        $user = User::factory()->create();
        $this->activateBankruptcyTechFor($user);
        $case = app(BankruptcyCaseService::class)->createCase($user, null, 'قضية');

        $response = $this->actingAs($user)->patch("/apps/bankruptcy-tech/cases/{$case->id}/wizard", ['is_active' => 'yes']);

        $response->assertRedirect();
        $this->assertStringEndsWith('#wizard', $response->headers->get('Location'));
    }

    /** نفس الإصلاح، لكن على مسار خطأ تحقق (وليس نجاح) — كان الخطأ يظهر ببانر بتبويب مختلف تمامًا. */
    public function test_creditor_validation_error_redirects_back_to_creditors_tab_not_overview(): void
    {
        $user = User::factory()->create();
        $this->activateBankruptcyTechFor($user);
        $case = app(BankruptcyCaseService::class)->createCase($user, null, 'قضية');

        $response = $this->actingAs($user)->post("/apps/bankruptcy-tech/cases/{$case->id}/creditors", ['name' => '', 'amount' => 1000, 'priority' => 'p3_unsecured']);

        $response->assertRedirect();
        $this->assertStringEndsWith('#creditors', $response->headers->get('Location'));
    }

    public function test_owner_can_delete_a_case_via_http(): void
    {
        $user = User::factory()->create();
        $this->activateBankruptcyTechFor($user);
        $case = app(BankruptcyCaseService::class)->createCase($user, null, 'قضية للحذف عبر HTTP');

        $this->actingAs($user)
            ->delete("/apps/bankruptcy-tech/cases/{$case->id}")
            ->assertRedirect('/apps/bankruptcy-tech');

        $this->assertDatabaseMissing('bankruptcy_cases', ['id' => $case->id]);
    }
}
