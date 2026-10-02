<?php

namespace App\Services\AI;

use App\Contracts\AIServiceInterface;
use App\Support\AIGenerationResult;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * تنفيذ AIServiceInterface الوحيد حاليًا (Anthropic Messages API مباشرة عبر
 * HTTP — بلا SDK إضافي، Just-In-Time حسب المخطط المعماري). أي تطبيق يستهلك
 * العقد فقط، لا هذا الصف مباشرة.
 */
class AnthropicAIService implements AIServiceInterface
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    private const API_VERSION = '2023-06-01';

    private const MAX_TOKENS = 4096;

    public function generate(string $systemPrompt, string $userPrompt): AIGenerationResult
    {
        $apiKey = config('services.anthropic.api_key');

        if (! $apiKey) {
            throw new RuntimeException('مفتاح Anthropic API غير مُهيَّأ — أضِف ANTHROPIC_API_KEY بملف .env.');
        }

        $model = config('services.anthropic.model');

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => self::API_VERSION,
            'content-type' => 'application/json',
        ])->timeout(120)->post(self::API_URL, [
            'model' => $model,
            'max_tokens' => self::MAX_TOKENS,
            'system' => $systemPrompt,
            'messages' => [['role' => 'user', 'content' => $userPrompt]],
        ]);

        if ($response->failed()) {
            throw new RuntimeException('فشل استدعاء نموذج الذكاء الاصطناعي (HTTP '.$response->status().'): '.$response->body());
        }

        $data = $response->json();
        $content = collect($data['content'] ?? [])->firstWhere('type', 'text')['text'] ?? '';

        if (trim($content) === '') {
            throw new RuntimeException('استجابة فارغة من نموذج الذكاء الاصطناعي.');
        }

        return new AIGenerationResult(
            content: $content,
            model: $data['model'] ?? $model,
            inputTokens: (int) ($data['usage']['input_tokens'] ?? 0),
            outputTokens: (int) ($data['usage']['output_tokens'] ?? 0),
        );
    }
}
