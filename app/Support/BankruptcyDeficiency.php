<?php

namespace App\Support;

/** نقص/ملاحظة تكتمل ملف القضية — قيمة ثابتة، لا Model، لا DB. */
readonly class BankruptcyDeficiency
{
    public function __construct(
        public string $severity, // critical|warning
        public string $message,
    ) {}
}
