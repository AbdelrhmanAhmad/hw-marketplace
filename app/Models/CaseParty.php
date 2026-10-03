<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['bankruptcy_case_id', 'name', 'role', 'identifier', 'contact', 'notes', 'added_by_user_id'])]
class CaseParty extends Model
{
    protected $table = 'bankruptcy_case_parties';

    public function bankruptcyCase(): BelongsTo
    {
        return $this->belongsTo(BankruptcyCase::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'debtor' => 'مدين',
            'creditor' => 'دائن',
            'trustee' => 'أمين تفليسة',
            default => 'طرف آخر',
        };
    }
}
