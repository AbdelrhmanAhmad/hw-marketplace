<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * سجل مالي مستقل عن CaseParty عمدًا (راجع رأس ملف Migration الجدول) — دائن
 * بمبلغ وأولوية نظامية (المادة 52)، لا علاقة إلزامية بأي CaseParty.
 */
#[Fillable(['bankruptcy_case_id', 'name', 'amount', 'priority', 'type', 'date', 'contact', 'pledge_type', 'pledge_registered', 'added_by_user_id'])]
class CaseCreditor extends Model
{
    protected $table = 'bankruptcy_case_creditors';

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date',
            'pledge_registered' => 'boolean',
        ];
    }

    public function bankruptcyCase(): BelongsTo
    {
        return $this->belongsTo(BankruptcyCase::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }

    /** ترتيب الأولوية النظامي (المادة 52) — للفرز بجدول الديون. */
    public function priorityRank(): int
    {
        return match ($this->priority) {
            'p1_expenses' => 1,
            'p1_employees' => 2,
            'p1_government' => 3,
            'p2_secured' => 4,
            'p3_unsecured' => 5,
            'p4_deferred' => 6,
            default => 99,
        };
    }

    public function priorityLabel(): string
    {
        return match ($this->priority) {
            'p1_expenses' => 'م1 — مصروفات الإجراء',
            'p1_employees' => 'م1 — مستحقات العمال',
            'p1_government' => 'م1 — ديون حكومية',
            'p2_secured' => 'م2 — دين مضمون برهن',
            'p3_unsecured' => 'م3 — دين تجاري عادي',
            'p4_deferred' => 'م4 — دين مؤخر (شركاء)',
            default => 'غير مصنَّف',
        };
    }
}
