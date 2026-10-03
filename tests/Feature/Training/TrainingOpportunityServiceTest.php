<?php

namespace Tests\Feature\Training;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\TrainingOpportunity;
use App\Models\User;
use App\Services\TrainingOpportunityService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * بوابة التدريب التعاوني — مكاتب تنشر فرصًا، طلاب يتقدّمون بمستندات
 * (سيرة ذاتية، إفادة قيد — إلزاميتان). لا بوابة موافقة إدارية للنشر (مطابق
 * لمجتمع الخدمات)، لكن التقديم يتطلب مستخدمًا مسجَّلًا دائمًا.
 */
class TrainingOpportunityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function opportunity(User $owner): TrainingOpportunity
    {
        return app(TrainingOpportunityService::class)->createOpportunity($owner, [
            'category' => 'قانوني', 'title' => 'تدريب تعاوني قانوني', 'description' => 'وصف كافٍ للفرصة.',
        ]);
    }

    /** @return array<string, UploadedFile> */
    private function requiredDocuments(): array
    {
        return [
            'cv' => UploadedFile::fake()->create('cv.pdf', 200, 'application/pdf'),
            'enrollment_letter' => UploadedFile::fake()->create('enrollment.pdf', 100, 'application/pdf'),
        ];
    }

    public function test_firm_can_create_an_opportunity(): void
    {
        $firm = User::factory()->create();

        $opportunity = $this->opportunity($firm);

        $this->assertSame('open', $opportunity->status);
        $this->assertTrue(AuditLog::where('event', AuditEvent::TrainingOpportunityCreated->value)->exists());
    }

    public function test_invalid_category_is_rejected(): void
    {
        $firm = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        app(TrainingOpportunityService::class)->createOpportunity($firm, [
            'category' => 'غير موجود', 'title' => 'عنوان', 'description' => 'وصف',
        ]);
    }

    public function test_owner_can_close_their_opportunity(): void
    {
        $firm = User::factory()->create();
        $opportunity = $this->opportunity($firm);

        app(TrainingOpportunityService::class)->closeOpportunity($firm, $opportunity);

        $this->assertSame('closed', $opportunity->fresh()->status);
        $this->assertNotNull($opportunity->fresh()->closed_at);
    }

    public function test_stranger_cannot_close_another_firms_opportunity(): void
    {
        $firm = User::factory()->create();
        $stranger = User::factory()->create();
        $opportunity = $this->opportunity($firm);

        $this->expectException(AuthorizationException::class);
        app(TrainingOpportunityService::class)->closeOpportunity($stranger, $opportunity);
    }

    public function test_student_can_apply_with_required_documents(): void
    {
        $firm = User::factory()->create();
        $student = User::factory()->create();
        $opportunity = $this->opportunity($firm);

        $application = app(TrainingOpportunityService::class)->submitApplication(
            $student, $opportunity, 'أنا مهتم.', $this->requiredDocuments()
        );

        $this->assertSame($student->id, $application->user_id);
        $this->assertSame(2, $application->documents()->count());
        $this->assertTrue(AuditLog::where('event', AuditEvent::TrainingApplicationSubmitted->value)->exists());
    }

    public function test_cannot_apply_without_the_cv(): void
    {
        $firm = User::factory()->create();
        $student = User::factory()->create();
        $opportunity = $this->opportunity($firm);

        $this->expectException(InvalidArgumentException::class);
        app(TrainingOpportunityService::class)->submitApplication($student, $opportunity, null, [
            'enrollment_letter' => UploadedFile::fake()->create('enrollment.pdf', 100, 'application/pdf'),
        ]);
    }

    public function test_firm_cannot_apply_to_their_own_opportunity(): void
    {
        $firm = User::factory()->create();
        $opportunity = $this->opportunity($firm);

        $this->expectException(InvalidArgumentException::class);
        app(TrainingOpportunityService::class)->submitApplication($firm, $opportunity, null, $this->requiredDocuments());
    }

    public function test_cannot_apply_twice(): void
    {
        $firm = User::factory()->create();
        $student = User::factory()->create();
        $opportunity = $this->opportunity($firm);
        app(TrainingOpportunityService::class)->submitApplication($student, $opportunity, null, $this->requiredDocuments());

        $this->expectException(InvalidArgumentException::class);
        app(TrainingOpportunityService::class)->submitApplication($student, $opportunity, null, $this->requiredDocuments());
    }

    public function test_cannot_apply_to_a_closed_opportunity(): void
    {
        $firm = User::factory()->create();
        $student = User::factory()->create();
        $opportunity = $this->opportunity($firm);
        app(TrainingOpportunityService::class)->closeOpportunity($firm, $opportunity);

        $this->expectException(InvalidArgumentException::class);
        app(TrainingOpportunityService::class)->submitApplication($student, $opportunity, null, $this->requiredDocuments());
    }
}
