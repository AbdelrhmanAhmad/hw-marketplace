<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * الجدول الزمني القانوني الثابت (8 مراحل نظامية، تُزرَع تلقائيًا عند إنشاء
 * القضية) — مختلف عن تبويب "سجل الأحداث" المبني على AuditLog.
 */
#[Fillable(['bankruptcy_case_id', 'label', 'day_offset', 'category', 'done', 'sort_order'])]
class CaseTimelineEvent extends Model
{
    protected $table = 'bankruptcy_case_timeline_events';

    /**
     * المراحل النظامية الثمانية الثابتة — تُزرَع تلقائيًا لكل قضية جديدة
     * عبر BankruptcyCaseService::createCase() (نسخ حرفي من
     * DEFAULT_TIMELINE_EVENTS بـhw-eflas).
     */
    public const array DEFAULTS = [
        ['label' => 'تقديم الطلب لقيد الدعاوى', 'day_offset' => 0, 'category' => 'info'],
        ['label' => 'فحص النواقص الشكلية بالإدارة', 'day_offset' => 3, 'category' => 'critical'],
        ['label' => 'تبليغ المدين', 'day_offset' => 5, 'category' => 'warning'],
        ['label' => 'صدور قرار المحكمة بالقبول/الرفض', 'day_offset' => 15, 'category' => 'critical'],
        ['label' => 'نشر الإعلان في الجريدة الرسمية', 'day_offset' => 30, 'category' => 'warning'],
        ['label' => 'تعيين لجنة الإفلاس', 'day_offset' => 45, 'category' => 'info'],
        ['label' => 'حصر الأصول وتقييمها', 'day_offset' => 60, 'category' => 'info'],
        ['label' => 'إصدار قائمة الدائنين المعتمدة', 'day_offset' => 90, 'category' => 'info'],
    ];

    protected function casts(): array
    {
        return [
            'done' => 'boolean',
        ];
    }

    public function bankruptcyCase(): BelongsTo
    {
        return $this->belongsTo(BankruptcyCase::class);
    }

    /** أيام متبقية اعتبارًا من submission_date + day_offset، سالب = متأخر. */
    public function daysRemaining(?string $submissionDate): ?int
    {
        if (! $submissionDate) {
            return null;
        }

        $due = \Carbon\Carbon::parse($submissionDate)->addDays($this->day_offset)->startOfDay();

        return (int) now()->startOfDay()->diffInDays($due, false);
    }
}
