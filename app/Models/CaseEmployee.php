<?php

namespace App\Models;

use App\Support\Eosb;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * لا عمود `benefits`/EOSB مُخزَّن عمدًا (قرار #4 بخطة المرحلة 1) — eosb()
 * يُحسَب حيًا دائمًا، مصدر حقيقة واحد لا يمكن أن يُصبح قديمًا (Stale).
 */
#[Fillable(['bankruptcy_case_id', 'name', 'nationality', 'iqama', 'salary', 'join_date', 'added_by_user_id'])]
class CaseEmployee extends Model
{
    protected $table = 'bankruptcy_case_employees';

    protected function casts(): array
    {
        return [
            'salary' => 'decimal:2',
            'join_date' => 'date',
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

    /** مكافأة نهاية الخدمة — محسوبة حيًا دائمًا، لا تُخزَّن أبدًا (المادة 84). */
    public function eosb(): int
    {
        return Eosb::calculate((float) $this->salary, $this->join_date?->toDateString());
    }
}
