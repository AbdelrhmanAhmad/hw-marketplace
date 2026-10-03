<?php

namespace Tests\Unit;

use App\Support\Eosb;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * نسخ حرفي لحالات eosb.test.ts بـhw-eflas — المادة 84: نصف راتب شهري ×
 * سنة لأول 5 سنوات، ثم راتب كامل × كل سنة بعدها. حالة 8/12 سنة تتحقق
 * صراحة من عدم الوقوع بخطأ "معدَّل مسطَّح" (Flat-rate) التاريخي.
 */
class EosbTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function joinDateYearsAgo(float $years): string
    {
        $now = Carbon::now();
        $seconds = (int) round($years * 365.25 * 86400);

        return $now->copy()->subSeconds($seconds)->toDateTimeString();
    }

    public function test_three_years_at_6000(): void
    {
        Carbon::setTestNow('2026-09-03 12:00:00');

        $this->assertSame(9000, Eosb::calculate(6000, $this->joinDateYearsAgo(3)));
    }

    public function test_exactly_five_years_at_6000(): void
    {
        Carbon::setTestNow('2026-09-03 12:00:00');

        $this->assertSame(15000, Eosb::calculate(6000, $this->joinDateYearsAgo(5)));
    }

    public function test_eight_years_at_5000_is_tiered_not_flat(): void
    {
        Carbon::setTestNow('2026-09-03 12:00:00');

        // 2500*5 + 5000*3 = 27500 — وليس 40000 (خطأ المعدَّل المسطَّح التاريخي).
        $this->assertSame(27500, Eosb::calculate(5000, $this->joinDateYearsAgo(8)));
        $this->assertNotSame(40000, Eosb::calculate(5000, $this->joinDateYearsAgo(8)));
    }

    public function test_twelve_years_at_5000(): void
    {
        Carbon::setTestNow('2026-09-03 12:00:00');

        // 2500*5 + 5000*7 = 47500
        $this->assertSame(47500, Eosb::calculate(5000, $this->joinDateYearsAgo(12)));
    }

    public function test_empty_join_date_returns_zero(): void
    {
        $this->assertSame(0, Eosb::calculate(6000, null));
        $this->assertSame(0, Eosb::calculate(6000, ''));
    }
}
