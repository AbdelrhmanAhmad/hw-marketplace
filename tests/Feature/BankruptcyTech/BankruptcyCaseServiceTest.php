<?php

namespace Tests\Feature\BankruptcyTech;

use App\Enums\AuditEvent;
use App\Enums\MembershipRole;
use App\Models\AuditLog;
use App\Models\BankruptcyCase;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Services\BankruptcyCaseService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Final Execution Sprint — إفلاس تك. كل Mutation عبر BankruptcyCaseService
 * حصرًا (BR-013). يغطي المسارين الشخصي والمؤسسي، وTenant Isolation.
 */
class BankruptcyCaseServiceTest extends TestCase
{
    use RefreshDatabase;

    private function orgWithOwnerAndMember(): array
    {
        $owner = User::factory()->create();
        $organization = Organization::create(['name' => 'مكتب اختبار إفلاس تك', 'type' => 'firm', 'owner_id' => $owner->id]);
        Membership::create(['user_id' => $owner->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Owner]);

        $member = User::factory()->create();
        Membership::create(['user_id' => $member->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);

        return [$owner, $organization, $member];
    }

    // --- إنشاء قضية ---

    public function test_user_can_create_personal_case(): void
    {
        $user = User::factory()->create();

        $case = app(BankruptcyCaseService::class)->createCase($user, null, 'قضية شخصية تجريبية');

        $this->assertNull($case->organization_id);
        $this->assertSame($user->id, $case->created_by_user_id);
        $this->assertSame('draft', $case->status);
        $this->assertStringStartsWith('BK-'.now()->year.'-', $case->case_number);
        $this->assertTrue(AuditLog::where('event', AuditEvent::CaseCreated->value)->where('subject_id', $case->id)->exists());
    }

    public function test_organization_member_can_create_case_for_organization(): void
    {
        [$owner, $organization, $member] = $this->orgWithOwnerAndMember();

        $case = app(BankruptcyCaseService::class)->createCase($member, $organization, 'قضية مؤسسية');

        $this->assertSame($organization->id, $case->organization_id);
    }

    public function test_stranger_cannot_create_case_for_organization_they_do_not_belong_to(): void
    {
        [, $organization] = $this->orgWithOwnerAndMember();
        $stranger = User::factory()->create();

        $this->expectException(AuthorizationException::class);
        app(BankruptcyCaseService::class)->createCase($stranger, $organization, 'محاولة تجاوز');
    }

    public function test_empty_title_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        app(BankruptcyCaseService::class)->createCase($user, null, '   ');
    }

    // --- تغيير الحالة ---

    public function test_owner_can_change_case_status(): void
    {
        [$owner, $organization] = $this->orgWithOwnerAndMember();
        $case = app(BankruptcyCaseService::class)->createCase($owner, $organization, 'قضية');

        app(BankruptcyCaseService::class)->changeStatus($owner, $case, 'preparing');

        $this->assertSame('preparing', $case->fresh()->status);
        $this->assertTrue(AuditLog::where('event', AuditEvent::CaseStatusChanged->value)->where('subject_id', $case->id)->exists());
    }

    public function test_plain_member_cannot_change_case_status(): void
    {
        [$owner, $organization, $member] = $this->orgWithOwnerAndMember();
        $case = app(BankruptcyCaseService::class)->createCase($owner, $organization, 'قضية');

        $this->expectException(AuthorizationException::class);
        app(BankruptcyCaseService::class)->changeStatus($member, $case, 'preparing');
    }

    public function test_closing_case_sets_closed_at(): void
    {
        [$owner, $organization] = $this->orgWithOwnerAndMember();
        $case = app(BankruptcyCaseService::class)->createCase($owner, $organization, 'قضية');

        app(BankruptcyCaseService::class)->changeStatus($owner, $case, 'closed');

        $this->assertNotNull($case->fresh()->closed_at);
    }

    // --- الأطراف/الإجراءات/الملاحظات: عضو عادي يقدر يساهم، لا يدير ---

    public function test_plain_member_can_add_party_and_note(): void
    {
        [$owner, $organization, $member] = $this->orgWithOwnerAndMember();
        $case = app(BankruptcyCaseService::class)->createCase($owner, $organization, 'قضية');
        $service = app(BankruptcyCaseService::class);

        $party = $service->addParty($member, $case, ['name' => 'شركة الفارس', 'role' => 'debtor']);
        $note = $service->addNote($member, $case, 'ملاحظة أولية');

        $this->assertSame('شركة الفارس', $party->name);
        $this->assertSame('ملاحظة أولية', $note->body);
    }

    public function test_invalid_party_role_is_rejected(): void
    {
        [$owner, $organization] = $this->orgWithOwnerAndMember();
        $case = app(BankruptcyCaseService::class)->createCase($owner, $organization, 'قضية');

        $this->expectException(InvalidArgumentException::class);
        app(BankruptcyCaseService::class)->addParty($owner, $case, ['name' => 'اسم', 'role' => 'invalid-role']);
    }

    public function test_procedure_lifecycle(): void
    {
        [$owner, $organization] = $this->orgWithOwnerAndMember();
        $case = app(BankruptcyCaseService::class)->createCase($owner, $organization, 'قضية');
        $service = app(BankruptcyCaseService::class);

        $procedure = $service->addProcedure($owner, $case, ['title' => 'إخطار الدائنين']);
        $this->assertSame('pending', $procedure->status);

        $service->updateProcedureStatus($owner, $procedure, 'completed');
        $this->assertSame('completed', $procedure->fresh()->status);
        $this->assertNotNull($procedure->fresh()->completed_at);
    }

    // --- المستندات: تخزين حقيقي ---

    public function test_document_upload_stores_real_file_and_is_downloadable(): void
    {
        Storage::fake('local');
        [$owner, $organization] = $this->orgWithOwnerAndMember();
        $case = app(BankruptcyCaseService::class)->createCase($owner, $organization, 'قضية');
        $file = UploadedFile::fake()->create('عقد.pdf', 100, 'application/pdf');

        $document = app(BankruptcyCaseService::class)->uploadDocument($owner, $case, $file, 'عقد التصفية');

        Storage::disk('local')->assertExists($document->path);
        $this->assertSame('عقد.pdf', $document->original_filename);
    }

    // --- Tenant Isolation — الفحص الأهم ---

    public function test_member_of_organization_a_cannot_view_case_of_organization_b(): void
    {
        [$ownerA, $organizationA] = $this->orgWithOwnerAndMember();
        [, $organizationB, $memberB] = $this->orgWithOwnerAndMember();

        $caseA = app(BankruptcyCaseService::class)->createCase($ownerA, $organizationA, 'قضية مؤسسة أ');

        $this->assertFalse(\Illuminate\Support\Facades\Gate::forUser($memberB)->allows('view', $caseA));
    }

    public function test_personal_case_is_invisible_to_other_users(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $case = app(BankruptcyCaseService::class)->createCase($owner, null, 'قضية شخصية');

        $this->assertFalse(\Illuminate\Support\Facades\Gate::forUser($stranger)->allows('view', $case));
        $this->assertTrue(\Illuminate\Support\Facades\Gate::forUser($owner)->allows('view', $case));
    }

    public function test_platform_staff_can_view_any_case(): void
    {
        [$owner, $organization] = $this->orgWithOwnerAndMember();
        $case = app(BankruptcyCaseService::class)->createCase($owner, $organization, 'قضية');
        $staff = User::factory()->create(['is_platform_staff' => true]);

        $this->assertTrue(\Illuminate\Support\Facades\Gate::forUser($staff)->allows('view', $case));
        $this->assertTrue(\Illuminate\Support\Facades\Gate::forUser($staff)->allows('manage', $case));
    }

    /** BankruptcyCase لا تُنشئ Membership أو أي أثر على Organization Domain — عزل صارم بين المجالين. */
    public function test_case_creation_does_not_create_membership_or_touch_organization_domain(): void
    {
        [$owner, $organization] = $this->orgWithOwnerAndMember();
        $membershipCountBefore = Membership::count();

        app(BankruptcyCaseService::class)->createCase($owner, $organization, 'قضية');

        $this->assertSame($membershipCountBefore, Membership::count());
    }

    // --- المرحلة 1: النموذج القانوني الكامل ---

    public function test_creating_a_case_seeds_the_eight_statutory_timeline_events(): void
    {
        $owner = User::factory()->create();
        $case = app(BankruptcyCaseService::class)->createCase($owner, null, 'قضية');

        $this->assertSame(8, $case->timelineEvents()->count());
        $this->assertSame(0, $case->timelineEvents()->orderBy('sort_order')->first()->day_offset);
    }

    public function test_plain_member_can_add_creditor_asset_and_employee(): void
    {
        [$owner, $organization, $member] = $this->orgWithOwnerAndMember();
        $case = app(BankruptcyCaseService::class)->createCase($owner, $organization, 'قضية');
        $service = app(BankruptcyCaseService::class);

        $service->addCreditor($member, $case, ['name' => 'دائن', 'amount' => 5000, 'priority' => 'p3_unsecured']);
        $service->addAsset($member, $case, ['name' => 'أصل', 'value' => 2000, 'location' => 'الرياض']);
        $service->addEmployee($member, $case, ['name' => 'موظف', 'salary' => 6000, 'join_date' => now()->subYears(3)->toDateString()]);

        $this->assertSame(1, $case->creditors()->count());
        $this->assertSame(1, $case->assets()->count());
        $this->assertSame(1, $case->employees()->count());
        $this->assertSame(5000.0, (float) $case->fresh()->total_debts);
        $this->assertSame(2000.0, (float) $case->fresh()->total_assets);
    }

    public function test_stranger_cannot_add_creditor_to_organization_case(): void
    {
        [$owner, $organization] = $this->orgWithOwnerAndMember();
        $case = app(BankruptcyCaseService::class)->createCase($owner, $organization, 'قضية');
        $stranger = User::factory()->create();

        $this->expectException(AuthorizationException::class);
        app(BankruptcyCaseService::class)->addCreditor($stranger, $case, ['name' => 'دائن', 'amount' => 1000, 'priority' => 'p3_unsecured']);
    }

    public function test_invalid_creditor_priority_is_rejected(): void
    {
        [$owner, $organization] = $this->orgWithOwnerAndMember();
        $case = app(BankruptcyCaseService::class)->createCase($owner, $organization, 'قضية');

        $this->expectException(InvalidArgumentException::class);
        app(BankruptcyCaseService::class)->addCreditor($owner, $case, ['name' => 'دائن', 'amount' => 1000, 'priority' => 'غير_معروف']);
    }

    public function test_toggle_timeline_event_flips_done_and_is_idempotent_when_toggled_twice(): void
    {
        [$owner, $organization] = $this->orgWithOwnerAndMember();
        $case = app(BankruptcyCaseService::class)->createCase($owner, $organization, 'قضية');
        $event = $case->timelineEvents()->first();

        app(BankruptcyCaseService::class)->toggleTimelineEvent($owner, $event);
        $this->assertTrue($event->fresh()->done);

        app(BankruptcyCaseService::class)->toggleTimelineEvent($owner, $event);
        $this->assertFalse($event->fresh()->done);
    }

    public function test_update_wizard_answers_rejects_unknown_token(): void
    {
        [$owner, $organization] = $this->orgWithOwnerAndMember();
        $case = app(BankruptcyCaseService::class)->createCase($owner, $organization, 'قضية');

        $this->expectException(InvalidArgumentException::class);
        app(BankruptcyCaseService::class)->updateWizardAnswers($owner, $case, ['is_active' => 'ربما']);
    }

    public function test_update_wizard_answers_and_checklists_persist_and_are_audited(): void
    {
        [$owner, $organization] = $this->orgWithOwnerAndMember();
        $case = app(BankruptcyCaseService::class)->createCase($owner, $organization, 'قضية');
        $service = app(BankruptcyCaseService::class);

        $service->updateWizardAnswers($owner, $case, ['insolvency_status' => 'upcoming', 'operated_twelve_months' => 'yes']);
        $service->updateChecklists($owner, $case, ['zatca_checklist' => ['accountStatement' => true]]);
        $service->updateDebtorProfile($owner, $case, ['debtor_name' => 'منشأة الاختبار']);

        $case->refresh();
        $this->assertSame('upcoming', $case->insolvency_status);
        $this->assertTrue($case->zatca_checklist['accountStatement']);
        $this->assertSame('منشأة الاختبار', $case->debtor_name);
        $this->assertTrue(AuditLog::where('event', AuditEvent::CaseWizardAnswersUpdated->value)->where('subject_id', $case->id)->exists());
        $this->assertTrue(AuditLog::where('event', AuditEvent::CaseChecklistsUpdated->value)->where('subject_id', $case->id)->exists());
        $this->assertTrue(AuditLog::where('event', AuditEvent::CaseProfileUpdated->value)->where('subject_id', $case->id)->exists());
    }

    public function test_member_of_organization_a_cannot_add_creditor_to_organization_b_case(): void
    {
        [$ownerA, $orgA] = $this->orgWithOwnerAndMember();
        [$ownerB, $orgB] = $this->orgWithOwnerAndMember();
        $caseA = app(BankruptcyCaseService::class)->createCase($ownerA, $orgA, 'قضية أ');

        $this->expectException(AuthorizationException::class);
        app(BankruptcyCaseService::class)->addCreditor($ownerB, $caseA, ['name' => 'دائن', 'amount' => 1000, 'priority' => 'p3_unsecured']);
    }

    // --- المرحلة 3: التوقيعات ---

    private function tinyPngDataUrl(): string
    {
        // أصغر PNG صالح فعليًا (1×1 شفاف) — يبدأ بنفس البادئة اللي يتحقق منها saveSignature.
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
    }

    public function test_owner_can_save_lawyer_signature(): void
    {
        $owner = User::factory()->create();
        $case = app(BankruptcyCaseService::class)->createCase($owner, null, 'قضية');

        app(BankruptcyCaseService::class)->saveSignature($owner, $case, 'lawyer', $this->tinyPngDataUrl());

        $this->assertNotNull($case->fresh()->lawyer_signature_data);
        $this->assertNull($case->fresh()->representative_signature_data);
        $this->assertTrue(AuditLog::where('event', AuditEvent::CaseSignatureSaved->value)->where('subject_id', $case->id)->exists());
    }

    public function test_signature_rejects_unknown_role(): void
    {
        $owner = User::factory()->create();
        $case = app(BankruptcyCaseService::class)->createCase($owner, null, 'قضية');

        $this->expectException(InvalidArgumentException::class);
        app(BankruptcyCaseService::class)->saveSignature($owner, $case, 'notary', $this->tinyPngDataUrl());
    }

    public function test_signature_rejects_non_png_data_url(): void
    {
        $owner = User::factory()->create();
        $case = app(BankruptcyCaseService::class)->createCase($owner, null, 'قضية');

        $this->expectException(InvalidArgumentException::class);
        app(BankruptcyCaseService::class)->saveSignature($owner, $case, 'lawyer', 'data:text/plain;base64,aGVsbG8=');
    }

    public function test_signature_rejects_oversized_payload(): void
    {
        $owner = User::factory()->create();
        $case = app(BankruptcyCaseService::class)->createCase($owner, null, 'قضية');
        $oversized = 'data:image/png;base64,'.str_repeat('A', 700_001);

        $this->expectException(InvalidArgumentException::class);
        app(BankruptcyCaseService::class)->saveSignature($owner, $case, 'lawyer', $oversized);
    }

    public function test_stranger_cannot_save_signature_on_organization_case(): void
    {
        [$owner, $organization] = $this->orgWithOwnerAndMember();
        $case = app(BankruptcyCaseService::class)->createCase($owner, $organization, 'قضية');
        $stranger = User::factory()->create();

        $this->expectException(AuthorizationException::class);
        app(BankruptcyCaseService::class)->saveSignature($stranger, $case, 'lawyer', $this->tinyPngDataUrl());
    }

    // --- إصلاحات ملاحظات المستخدم: حذف القضية ---

    public function test_owner_can_delete_case_and_it_cascades_children(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $case = app(BankruptcyCaseService::class)->createCase($owner, null, 'قضية للحذف');
        app(BankruptcyCaseService::class)->addCreditor($owner, $case, ['name' => 'دائن', 'amount' => 1000, 'priority' => 'p3_unsecured']);
        $document = app(BankruptcyCaseService::class)->uploadDocument($owner, $case, UploadedFile::fake()->create('م.pdf', 10), 'مستند');
        $path = $document->path;

        app(BankruptcyCaseService::class)->deleteCase($owner, $case);

        $this->assertDatabaseMissing('bankruptcy_cases', ['id' => $case->id]);
        $this->assertDatabaseMissing('bankruptcy_case_creditors', ['bankruptcy_case_id' => $case->id]);
        $this->assertDatabaseMissing('bankruptcy_case_timeline_events', ['bankruptcy_case_id' => $case->id]);
        Storage::disk('local')->assertMissing($path);
        $this->assertTrue(AuditLog::where('event', AuditEvent::CaseDeleted->value)->where('subject_id', $case->id)->exists());
    }

    public function test_plain_member_cannot_delete_organization_case(): void
    {
        [$owner, $organization, $member] = $this->orgWithOwnerAndMember();
        $case = app(BankruptcyCaseService::class)->createCase($owner, $organization, 'قضية');

        $this->expectException(AuthorizationException::class);
        app(BankruptcyCaseService::class)->deleteCase($member, $case);
    }
}
