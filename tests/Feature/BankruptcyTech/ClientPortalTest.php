<?php

namespace Tests\Feature\BankruptcyTech;

use App\Enums\AuditEvent;
use App\Enums\MembershipRole;
use App\Models\AuditLog;
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
 * إفلاس تك — المرحلة 2: بوابة العميل الخارجية (المدين). يغطي: صلاحية الدعوة
 * (manage فقط)، عزل العميل عن Entitlement (لا يقدر يدخل مسار المحامي)،
 * IDOR بين قضيتين، الإلغاء/الاستعادة، رفع مستند فعلي.
 */
class ClientPortalTest extends TestCase
{
    use RefreshDatabase;

    // --- الدعوة (Service-level) ---

    public function test_owner_can_invite_a_client(): void
    {
        $owner = User::factory()->create();
        $case = app(BankruptcyCaseService::class)->createCase($owner, null, 'قضية');

        $client = app(BankruptcyCaseService::class)->inviteClient($owner, $case, 'عميل تجريبي', 'client@example.test');

        $this->assertSame('client@example.test', $client->email);
        $this->assertSame($client->id, $case->fresh()->client_user_id);
        $this->assertTrue($case->fresh()->hasActiveClient());
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'client@example.test']);
        $this->assertTrue(AuditLog::where('event', AuditEvent::CaseClientInvited->value)->where('subject_id', $case->id)->exists());
    }

    public function test_plain_member_cannot_invite_a_client(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::create(['name' => 'مكتب اختبار', 'type' => 'firm', 'owner_id' => $owner->id]);
        Membership::create(['user_id' => $owner->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Owner]);
        $member = User::factory()->create();
        Membership::create(['user_id' => $member->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);
        $case = app(BankruptcyCaseService::class)->createCase($owner, $organization, 'قضية');

        $this->expectException(AuthorizationException::class);
        app(BankruptcyCaseService::class)->inviteClient($member, $case, 'عميل', 'x@example.test');
    }

    public function test_inviting_a_client_with_an_already_registered_email_is_rejected(): void
    {
        $owner = User::factory()->create();
        $existing = User::factory()->create(['email' => 'taken@example.test']);
        $case = app(BankruptcyCaseService::class)->createCase($owner, null, 'قضية');

        $this->expectException(InvalidArgumentException::class);
        app(BankruptcyCaseService::class)->inviteClient($owner, $case, 'عميل', $existing->email);
    }

    public function test_cannot_invite_a_second_client_to_the_same_case(): void
    {
        $owner = User::factory()->create();
        $case = app(BankruptcyCaseService::class)->createCase($owner, null, 'قضية');
        app(BankruptcyCaseService::class)->inviteClient($owner, $case, 'عميل 1', 'one@example.test');

        $this->expectException(InvalidArgumentException::class);
        app(BankruptcyCaseService::class)->inviteClient($owner, $case, 'عميل 2', 'two@example.test');
    }

    // --- بوابة العميل عبر HTTP ---

    public function test_client_can_view_their_own_case(): void
    {
        $owner = User::factory()->create();
        $case = app(BankruptcyCaseService::class)->createCase($owner, null, 'قضية العميل');
        $client = app(BankruptcyCaseService::class)->inviteClient($owner, $case, 'عميل', 'client@example.test');

        $this->actingAs($client)
            ->get("/client-portal/cases/{$case->id}")
            ->assertOk()
            ->assertSee('قضية العميل');
    }

    /** حاسم: العميل ليس مشترك Marketplace — يُرفَض عن مسار المحامي رغم كونه client_user_id على نفس القضية. */
    public function test_client_cannot_access_the_lawyer_facing_route_for_their_own_case(): void
    {
        $this->seed(\Database\Seeders\MarketplaceCatalogSeeder::class);
        $owner = User::factory()->create();
        $case = app(BankruptcyCaseService::class)->createCase($owner, null, 'قضية');
        $client = app(BankruptcyCaseService::class)->inviteClient($owner, $case, 'عميل', 'client@example.test');

        $this->actingAs($client)
            ->get("/apps/bankruptcy-tech/cases/{$case->id}")
            ->assertForbidden();
    }

    /** IDOR: عميل قضية أ يحاول يفتح قضية ب عبر تركيب رابط. */
    public function test_client_cannot_view_a_different_case(): void
    {
        $ownerA = User::factory()->create();
        $caseA = app(BankruptcyCaseService::class)->createCase($ownerA, null, 'قضية أ');
        app(BankruptcyCaseService::class)->inviteClient($ownerA, $caseA, 'عميل أ', 'clienta@example.test');

        $ownerB = User::factory()->create();
        $caseB = app(BankruptcyCaseService::class)->createCase($ownerB, null, 'قضية ب');
        $clientB = app(BankruptcyCaseService::class)->inviteClient($ownerB, $caseB, 'عميل ب', 'clientb@example.test');

        $this->actingAs($clientB)->get("/client-portal/cases/{$caseA->id}")->assertForbidden();
    }

    public function test_revoked_client_is_rejected_and_restore_reinstates_access(): void
    {
        $owner = User::factory()->create();
        $case = app(BankruptcyCaseService::class)->createCase($owner, null, 'قضية');
        $client = app(BankruptcyCaseService::class)->inviteClient($owner, $case, 'عميل', 'client@example.test');

        app(BankruptcyCaseService::class)->revokeClientAccess($owner, $case);
        $this->actingAs($client)->get("/client-portal/cases/{$case->id}")->assertForbidden();

        app(BankruptcyCaseService::class)->restoreClientAccess($owner, $case);
        $this->actingAs($client)->get("/client-portal/cases/{$case->id}")->assertOk();
    }

    public function test_client_can_upload_a_document_which_the_lawyer_also_sees(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $case = app(BankruptcyCaseService::class)->createCase($owner, null, 'قضية');
        $client = app(BankruptcyCaseService::class)->inviteClient($owner, $case, 'عميل', 'client@example.test');

        $this->actingAs($client)->post("/client-portal/cases/{$case->id}/documents", [
            'title' => 'مستند من العميل', 'file' => UploadedFile::fake()->create('عقد.pdf', 50),
        ])->assertRedirect();

        $this->assertSame(1, $case->fresh()->documents()->count());
        $document = $case->fresh()->documents()->first();
        $this->assertSame('مستند من العميل', $document->title);
        $this->assertSame($client->id, $document->uploaded_by_user_id);
    }

    public function test_client_cannot_upload_a_document_to_a_different_case(): void
    {
        $ownerA = User::factory()->create();
        $caseA = app(BankruptcyCaseService::class)->createCase($ownerA, null, 'قضية أ');

        $ownerB = User::factory()->create();
        $caseB = app(BankruptcyCaseService::class)->createCase($ownerB, null, 'قضية ب');
        $clientB = app(BankruptcyCaseService::class)->inviteClient($ownerB, $caseB, 'عميل ب', 'clientb@example.test');

        $this->actingAs($clientB)->post("/client-portal/cases/{$caseA->id}/documents", [
            'title' => 'محاولة تسلل', 'file' => UploadedFile::fake()->create('x.pdf', 10),
        ])->assertForbidden();
    }

    public function test_guest_is_redirected_to_login_from_client_portal(): void
    {
        $owner = User::factory()->create();
        $case = app(BankruptcyCaseService::class)->createCase($owner, null, 'قضية');

        $this->get("/client-portal/cases/{$case->id}")->assertRedirect('/login');
    }
}
