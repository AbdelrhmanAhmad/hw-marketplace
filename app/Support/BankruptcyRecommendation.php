<?php

namespace App\Support;

/** نتيجة محرك التصنيف الحتمي — قيمة ثابتة (Value Object)، لا Model، لا DB. */
readonly class BankruptcyRecommendation
{
    /** @param  string[]  $articles */
    public function __construct(
        public string $code,
        public string $title,
        public string $reason,
        public array $articles,
    ) {}
}
