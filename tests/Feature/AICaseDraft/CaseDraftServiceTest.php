<?php

namespace Tests\Feature\AICaseDraft;

use App\Contracts\AIServiceInterface;
use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\CaseDraftGeneration;
use App\Models\User;
use App\Services\BankruptcyCaseService;
use App\Services\CaseDraftService;
use App\Support\AIGenerationResult;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/**
 * محرك مسودة القضية الذكي — لا استدعاء API حقيقي بالاختبارات؛ AIServiceInterface
 * تُستبدَل بتنفيذ وهمي (Fake) — يختبر منطق التطبيق (Authorization، تسجيل
 * التدقيق، معالجة الفشل)، لا سلوك مزوّد AI فعلي (خارج نطاق اختبار الوحدة).
 */
class CaseDraftServiceTest extends TestCase
{
    use RefreshDatabase;

    private function bindFakeAI(string $content = 'مسودة تجريبية للمذكرة.', int $inputTokens = 100, int $outputTokens = 200): void
    {
        $this->app->bind(AIServiceInterface::class, fn () => new class($content, $inputTokens, $outputTokens) implements AIServiceInterface {
            public function __construct(private string $content, private int $in, private int $out) {}

            public function generate(string $systemPrompt, string $userPrompt): AIGenerationResult
            {
                return new AIGenerationResult($this->content, 'claude-fake-test', $this->in, $this->out);
            }
        });
    }

    private function bindFailingAI(string $message = 'تعذّر الاتصال بالمزوّد.'): void
    {
        $this->app->bind(AIServiceInterface::class, fn () => new class($message) implements AIServiceInterface {
            public function __construct(private string $message) {}

            public function generate(string $systemPrompt, string $userPrompt): AIGenerationResult
            {
                throw new RuntimeException($this->message);
            }
        });
    }

    public function test_case_owner_can_generate_a_draft(): void
    {
        $this->bindFakeAI();
        $user = User::factory()->create();
        $case = app(BankruptcyCaseService::class)->createCase($user, null, 'قضية اختبار المسودة الذكية');

        $generation = app(CaseDraftService::class)->generateDraft($user, $case);

        $this->assertSame('completed', $generation->status);
        $this->assertSame('مسودة تجريبية للمذكرة.', $generation->content);
        $this->assertSame(100, $generation->input_tokens);
        $this->assertSame(200, $generation->output_tokens);
        $this->assertNotNull($generation->estimated_cost_usd);
        $this->assertTrue(AuditLog::where('event', AuditEvent::CaseDraftGenerated->value)->exists());
    }

    public function test_stranger_cannot_generate_a_draft_for_another_users_case(): void
    {
        $this->bindFakeAI();
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $case = app(BankruptcyCaseService::class)->createCase($owner, null, 'قضية خاصة');

        $this->expectException(AuthorizationException::class);
        app(CaseDraftService::class)->generateDraft($stranger, $case);
    }

    public function test_failed_generation_is_recorded_and_not_silently_swallowed(): void
    {
        $this->bindFailingAI('انقطع الاتصال بمزوّد الذكاء الاصطناعي.');
        $user = User::factory()->create();
        $case = app(BankruptcyCaseService::class)->createCase($user, null, 'قضية اختبار الفشل');

        $this->expectException(InvalidArgumentException::class);

        try {
            app(CaseDraftService::class)->generateDraft($user, $case);
        } finally {
            $failed = CaseDraftGeneration::where('bankruptcy_case_id', $case->id)->first();
            $this->assertNotNull($failed);
            $this->assertSame('failed', $failed->status);
            $this->assertSame('انقطع الاتصال بمزوّد الذكاء الاصطناعي.', $failed->error_message);
            $this->assertTrue(AuditLog::where('event', AuditEvent::CaseDraftGenerationFailed->value)->exists());
        }
    }

    public function test_generation_record_captures_the_model_and_prompt_version(): void
    {
        $this->bindFakeAI();
        $user = User::factory()->create();
        $case = app(BankruptcyCaseService::class)->createCase($user, null, 'قضية اختبار');

        $generation = app(CaseDraftService::class)->generateDraft($user, $case);

        $this->assertSame('claude-fake-test', $generation->model);
        $this->assertNotEmpty($generation->prompt_version);
    }
}
