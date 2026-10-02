<?php

namespace App\Services;

use App\Contracts\AIServiceInterface;
use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\BankruptcyCase;
use App\Models\CaseDraftGeneration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use RuntimeException;

/**
 * محرك مسودة القضية الذكي — نقطة الدخول الوحيدة لتوليد مسودة (BR-013).
 * يطبّق قيود docs/marketplace-architecture-blueprint.md §7 حرفيًا:
 *
 *   - Permission & Tenant-aware context: BankruptcyCasePolicy::view نفسها
 *     المستخدَمة للبشر — الذكاء الاصطناعي لا يرى شيئًا المستخدم لا يقدر يراه.
 *   - Model Selection + Prompt Versioning: كل سجل يحمل النموذج ونسخة الـPrompt.
 *   - Data Provenance: السياق يُبنى حصرًا من بيانات القضية المُخزَّنة (لا
 *     مصادر خارجية، لا اختراع حقائق).
 *   - Usage & Cost Tracking + Audit Logs: سجل CaseDraftGeneration نفسه غير
 *     قابل للتعديل (Create فقط)، مع AuditLog مرافق (BR-013 المعتاد).
 */
class CaseDraftService
{
    private const PROMPT_VERSION = 'case-draft-v1';

    /** تسعير تقديري لكل مليون Token (Claude Sonnet) — لتتبّع التكلفة داخليًا فقط، ليس فوترة دقيقة. */
    private const INPUT_COST_PER_MILLION = 3.0;

    private const OUTPUT_COST_PER_MILLION = 15.0;

    public function __construct(private readonly AIServiceInterface $ai)
    {
    }

    public function generateDraft(User $actor, BankruptcyCase $case): CaseDraftGeneration
    {
        Gate::forUser($actor)->authorize('view', $case);

        $model = config('services.anthropic.model');

        try {
            $result = $this->ai->generate($this->systemPrompt(), $this->userPrompt($case));
        } catch (RuntimeException $e) {
            $generation = CaseDraftGeneration::create([
                'bankruptcy_case_id' => $case->id,
                'requested_by_user_id' => $actor->id,
                'organization_id' => $case->organization_id,
                'status' => 'failed',
                'model' => $model,
                'prompt_version' => self::PROMPT_VERSION,
                'error_message' => $e->getMessage(),
            ]);

            $this->log($actor, AuditEvent::CaseDraftGenerationFailed, $generation, $case->organization_id, ['bankruptcy_case_id' => $case->id]);

            throw new InvalidArgumentException($e->getMessage());
        }

        return DB::transaction(function () use ($actor, $case, $result) {
            $generation = CaseDraftGeneration::create([
                'bankruptcy_case_id' => $case->id,
                'requested_by_user_id' => $actor->id,
                'organization_id' => $case->organization_id,
                'status' => 'completed',
                'model' => $result->model,
                'prompt_version' => self::PROMPT_VERSION,
                'content' => $result->content,
                'input_tokens' => $result->inputTokens,
                'output_tokens' => $result->outputTokens,
                'estimated_cost_usd' => $this->estimateCost($result->inputTokens, $result->outputTokens),
            ]);

            $this->log($actor, AuditEvent::CaseDraftGenerated, $generation, $case->organization_id, [
                'bankruptcy_case_id' => $case->id,
                'model' => $result->model,
                'input_tokens' => $result->inputTokens,
                'output_tokens' => $result->outputTokens,
            ]);

            return $generation;
        });
    }

    private function estimateCost(int $inputTokens, int $outputTokens): float
    {
        return round(
            ($inputTokens / 1_000_000 * self::INPUT_COST_PER_MILLION)
            + ($outputTokens / 1_000_000 * self::OUTPUT_COST_PER_MILLION),
            4
        );
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
            أنت مساعد قانوني متخصص بنظام الإفلاس السعودي، تساعد محامين مرخّصين
            بإعداد مسودة أولية لمذكرة قضية إفلاس. اعتمد حصرًا على البيانات
            المُعطاة لك بالسياق أدناه — لا تخترع أسماء، أرقامًا، أو وقائع غير
            موجودة بها. لو كانت بيانات جوهرية ناقصة، اذكر ذلك صراحة كملاحظة
            بدل افتراضها. المخرج مسودة أولية للمراجعة البشرية فقط — اختم دائمًا
            بتنويه واضح إنها مسودة تحتاج مراجعة وتدقيق محامٍ مرخّص قبل أي
            استخدام رسمي أو تقديم للمحكمة.
            PROMPT;
    }

    private function userPrompt(BankruptcyCase $case): string
    {
        $creditors = $case->creditors->map(fn ($c) => "- {$c->name}: {$c->amount} ريال (أولوية: {$c->priority})")->implode("\n") ?: 'لا يوجد دائنون مُسجَّلون بعد.';
        $assets = $case->assets->map(fn ($a) => "- {$a->name}: {$a->value} ريال".($a->location ? " ({$a->location})" : ''))->implode("\n") ?: 'لا توجد أصول مُسجَّلة بعد.';
        $parties = $case->parties->map(fn ($p) => "- {$p->name} ({$p->role})")->implode("\n") ?: 'لا أطراف مُسجَّلة بعد.';
        $procedures = $case->procedures->map(fn ($p) => "- {$p->title}: {$p->status}")->implode("\n") ?: 'لا إجراءات مُسجَّلة بعد.';

        return <<<PROMPT
            أعِدّ مسودة أولية لمذكرة افتتاحية بقضية الإفلاس التالية:

            بيانات القضية:
            - رقم القضية: {$case->case_number}
            - المدين: {$case->debtor_name}
            - السجل التجاري: {$case->cr_number}
            - الشكل النظامي: {$case->legal_form}
            - المحامي: {$case->attorney_name}
            - حالة القضية: {$case->status}
            - إجمالي الديون المُسجَّلة: {$case->total_debts} ريال
            - إجمالي الأصول المُسجَّلة: {$case->total_assets} ريال

            الدائنون:
            {$creditors}

            الأصول:
            {$assets}

            الأطراف:
            {$parties}

            الإجراءات المُسجَّلة:
            {$procedures}

            المطلوب: مسودة مذكرة افتتاحية منظّمة (تمهيد، وقائع، الوضع المالي،
            الطلبات) بالاستناد لهذي البيانات فقط.
            PROMPT;
    }

    private function log(User $actor, AuditEvent $event, $subject, ?int $organizationId, array $metadata = []): void
    {
        AuditLog::create([
            'organization_id' => $organizationId,
            'actor_user_id' => $actor->id,
            'event' => $event->value,
            'subject_type' => $subject::class,
            'subject_id' => $subject->id,
            'metadata' => $metadata,
        ]);
    }
}
