<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * مكافأة نهاية الخدمة (نظام العمل، المادة 84) — مصدر حقيقة واحد، نسخ حرفي
 * من eosb.ts بـhw-eflas: نصف راتب شهري × سنة لأول 5 سنوات، ثم راتب كامل ×
 * كل سنة إضافية بعدها — **وليس** معدَّلًا مسطَّحًا على كامل مدة الخدمة.
 */
class Eosb
{
    public static function calculate(float $salary, ?string $joinDate): int
    {
        if (! $joinDate) {
            return 0;
        }

        // فرق زمني متصل (بالثواني)، وليس بعدد أيام صحيح — نفس دقة eosb.ts
        // الأصلي (فرق بالميلي ثانية) لضمان تطابق حدود السنوات بالضبط.
        $years = (now()->timestamp - Carbon::parse($joinDate)->timestamp) / (365.25 * 86400);

        if ($years <= 0) {
            return 0;
        }

        if ($years <= 5) {
            return (int) round(($salary / 2) * $years);
        }

        return (int) round(($salary / 2) * 5 + $salary * ($years - 5));
    }
}
