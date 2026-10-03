<?php

namespace Tests\Feature\AICaseDraft;

use App\Contracts\AIServiceInterface;
use App\Models\CaseDraftGeneration;
use App\Models\MarketplaceItem;
use App\Models\User;
use App\Services\BankruptcyCaseService;
use App\Services\SubscriptionService;
use App\Support\AIGenerationResult;
use Database\Seeders\MarketplaceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * محرك مسودة القضية الذكي عبر HTTP — عنصر كتالوج مستقل (ai-case-draft) فوق
 * bankruptcy-tech نفسها: يتطلب الاثنين معًا (Entitlement مزدوج، طبقة إضافية
 * صريحة). لا استدعاء API حقيقي — AIServiceInterface وهمية بالاختبارات.
 */
class CaseDraftHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(AIServiceInterface::class, fn () => new class implements AIServiceInterface {
            public function generate(string $systemPrompt, string $userPrompt): AIGenerationResult
            {
                return new AIGenerationResult('مسودة تجريبية عبر HTTP.', 'claude-fake-test', 50, 150);
            }
        });
    }

    private function activateBoth(User $user): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $subscriptions = app(SubscriptionService::class);
        $subscriptions->subscribeUserToFreeItem($user, MarketplaceItem::where('key', 'bankruptcy-tech')->firstOrFail());
        $subscriptions->subscribeUserToFreeItem($user, MarketplaceItem::where('key', 'ai-case-draft')->firstOrFail());
    }

    public function test_generating_a_draft_requires_the_ai_case_draft_entitlement_even_with_bankruptcy_tech_active(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $user = User::factory()->create();
        app(SubscriptionService::class)->subscribeUserToFreeItem($user, MarketplaceItem::where('key', 'bankruptcy-tech')->firstOrFail());
        $case = app(BankruptcyCaseService::class)->createCase($user, null, 'قضية بلا تفعيل AI');

        $this->actingAs($user)->post("/apps/bankruptcy-tech/cases/{$case->id}/ai-draft")->assertForbidden();
        $this->assertSame(0, CaseDraftGeneration::count());
    }

    public function test_full_generation_flow_via_real_http_request(): void
    {
        $user = User::factory()->create();
        $this->activateBoth($user);
        $case = app(BankruptcyCaseService::class)->createCase($user, null, 'قضية اختبار HTTP للمسودة الذكية');

        $response = $this->actingAs($user)->post("/apps/bankruptcy-tech/cases/{$case->id}/ai-draft");
        $response->assertRedirect();
        $this->assertStringContainsString('#ai-draft', $response->headers->get('Location'));

        $generation = CaseDraftGeneration::where('bankruptcy_case_id', $case->id)->firstOrFail();
        $this->assertSame('completed', $generation->status);

        $this->actingAs($user)
            ->get("/apps/bankruptcy-tech/cases/{$case->id}")
            ->assertOk()
            ->assertSee('مسودة تجريبية عبر HTTP.');
    }

    public function test_stranger_cannot_generate_a_draft_for_another_users_case_via_http(): void
    {
        $owner = User::factory()->create();
        $this->activateBoth($owner);
        $case = app(BankruptcyCaseService::class)->createCase($owner, null, 'قضية خاصة بالمالك');

        $stranger = User::factory()->create();
        $this->activateBoth($stranger);

        $this->actingAs($stranger)->post("/apps/bankruptcy-tech/cases/{$case->id}/ai-draft")->assertForbidden();
    }
}
