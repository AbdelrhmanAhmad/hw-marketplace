<?php

namespace App\Contracts;

use App\Support\AIGenerationResult;

/**
 * طبقة AI المشتركة (docs/marketplace-architecture-blueprint.md §7) — "لا
 * نبني تطبيق ذكاء اصطناعي، نبني طبقة تُستهلَك من أي تطبيق يحتاجها". كل
 * تطبيق يتعامل مع هذا العقد فقط، لا استدعاء مباشر لمزوّد نموذج بكوده الخاص
 * (LLM Gateway) — يسمح بتبديل المزوّد لاحقًا بلا كسر أي تطبيق مستهلك.
 */
interface AIServiceInterface
{
    public function generate(string $systemPrompt, string $userPrompt): AIGenerationResult;
}
