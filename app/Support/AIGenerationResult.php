<?php

namespace App\Support;

/**
 * نتيجة استدعاء AI موحّدة — بغض النظر عن المزوّد خلف AIServiceInterface
 * (راجع docs/marketplace-architecture-blueprint.md §7: Model Selection
 * قابل للتتبّع، Usage & Cost Tracking إلزامي لكل استدعاء).
 */
final class AIGenerationResult
{
    public function __construct(
        public readonly string $content,
        public readonly string $model,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
    ) {
    }
}
