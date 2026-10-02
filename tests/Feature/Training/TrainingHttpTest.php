<?php

namespace Tests\Feature\Training;

use App\Models\MarketplaceItem;
use App\Models\TrainingApplication;
use App\Models\TrainingOpportunity;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Services\TrainingOpportunityService;
use Database\Seeders\MarketplaceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * بوابة التدريب التعاوني عبر HTTP — الصفحة العامة (/internships) بلا Auth
 * (مطابقة لمجتمع الخدمات)، لكن التقديم وحده يتطلب تسجيل دخول + مستندات
 * إلزامية (سيرة ذاتية، إفادة قيد). لوحة "فرصي" (/apps/internships) خلف
 * marketplace.entitled:internships.
 */
class TrainingHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function activate(User $user): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $item = MarketplaceItem::where('key', 'internships')->firstOrFail();
        app(SubscriptionService::class)->subscribeUserToFreeItem($user, $item);
    }

    private function openOpportunity(User $firm): TrainingOpportunity
    {
        return app(TrainingOpportunityService::class)->createOpportunity($firm, [
            'category' => 'قانوني', 'title' => 'تدريب تعاوني قانوني', 'description' => 'وصف كافٍ للفرصة المعروضة.',
        ]);
    }

    /** @return array<string, UploadedFile> */
    private function requiredDocumentFiles(): array
    {
        return [
            'cv' => UploadedFile::fake()->create('cv.pdf', 200, 'application/pdf'),
            'enrollment_letter' => UploadedFile::fake()->create('enrollment.pdf', 100, 'application/pdf'),
        ];
    }

    public function test_guest_can_browse_the_public_board(): void
    {
        $firm = User::factory()->create();
        $this->openOpportunity($firm);

        $this->get('/internships')->assertOk()->assertSee('تدريب تعاوني قانوني');
    }

    public function test_guest_gets_404_for_a_closed_opportunity(): void
    {
        $firm = User::factory()->create();
        $opportunity = $this->openOpportunity($firm);
        app(TrainingOpportunityService::class)->closeOpportunity($firm, $opportunity);

        $this->get("/internships/{$opportunity->id}")->assertNotFound();
    }

    public function test_guest_cannot_apply_and_is_redirected_to_login(): void
    {
        $firm = User::factory()->create();
        $opportunity = $this->openOpportunity($firm);

        $this->post("/internships/{$opportunity->id}/apply")->assertRedirect('/login');
        $this->assertSame(0, TrainingApplication::count());
    }

    public function test_dashboard_requires_activating_the_free_app_first(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/apps/internships')->assertForbidden();
    }

    public function test_application_without_required_documents_is_rejected(): void
    {
        $firm = User::factory()->create();
        $opportunity = $this->openOpportunity($firm);
        $student = User::factory()->create();

        $this->actingAs($student)->post("/internships/{$opportunity->id}/apply", [
            'message' => 'بلا مستندات.',
        ])->assertRedirect()->assertSessionHasErrors(['cv', 'enrollment_letter']);

        $this->assertSame(0, TrainingApplication::count());
    }

    public function test_full_posting_and_application_flow_via_real_http_requests(): void
    {
        $firm = User::factory()->create();
        $this->activate($firm);

        $this->actingAs($firm)->get('/apps/internships')->assertOk();

        $this->actingAs($firm)->post('/apps/internships', [
            'category' => 'قانوني',
            'title' => 'تدريب تعاوني عبر HTTP',
            'description' => 'وصف كافٍ للفرصة المعروضة.',
            'location' => 'الرياض',
            'duration' => '3 أشهر',
        ])->assertRedirect();

        $opportunity = TrainingOpportunity::where('title', 'تدريب تعاوني عبر HTTP')->firstOrFail();

        // ظاهرة مباشرة للعامة بلا مراجعة.
        $this->get('/internships')->assertOk()->assertSee('تدريب تعاوني عبر HTTP');

        $student = User::factory()->create();
        $this->actingAs($student)->post("/internships/{$opportunity->id}/apply", [
            'message' => 'مهتم فعليًا بالتدريب.',
            ...$this->requiredDocumentFiles(),
        ])->assertRedirect();

        $application = TrainingApplication::where('training_opportunity_id', $opportunity->id)->firstOrFail();
        $this->assertSame(2, $application->documents()->count());

        $firmDashboard = $this->actingAs($firm)->get("/apps/internships/{$opportunity->id}");
        $firmDashboard->assertOk()->assertSee($student->name)->assertSee('السيرة الذاتية');

        $document = $application->documents()->where('type', 'cv')->firstOrFail();
        $this->actingAs($firm)
            ->get("/apps/internships/{$opportunity->id}/applications/{$application->id}/documents/{$document->id}/download")
            ->assertOk();

        // غريب ما يقدر يحمّل مستند فرصة غيره (IDOR).
        $stranger = User::factory()->create();
        $this->activate($stranger);
        $this->actingAs($stranger)
            ->get("/apps/internships/{$opportunity->id}/applications/{$application->id}/documents/{$document->id}/download")
            ->assertForbidden();

        $this->actingAs($firm)->post("/apps/internships/{$opportunity->id}/close")->assertRedirect();
        $this->assertSame('closed', $opportunity->fresh()->status);

        $this->get('/internships')->assertOk()->assertSee('لا توجد فرص');
    }

    public function test_stranger_cannot_close_another_firms_opportunity_via_http(): void
    {
        $firm = User::factory()->create();
        $this->activate($firm);
        $opportunity = $this->openOpportunity($firm);

        $stranger = User::factory()->create();
        $this->activate($stranger);

        $this->actingAs($stranger)->post("/apps/internships/{$opportunity->id}/close")->assertForbidden();
        $this->assertSame('open', $opportunity->fresh()->status);
    }

    public function test_stranger_cannot_view_another_firms_opportunity_management_page(): void
    {
        $firm = User::factory()->create();
        $this->activate($firm);
        $opportunity = $this->openOpportunity($firm);

        $stranger = User::factory()->create();
        $this->activate($stranger);

        $this->actingAs($stranger)->get("/apps/internships/{$opportunity->id}")->assertForbidden();
    }
}
