<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['bankruptcy_case_id', 'date', 'type', 'notes', 'result', 'added_by_user_id'])]
class CaseHearing extends Model
{
    protected $table = 'bankruptcy_case_hearings';

    protected function casts(): array
    {
        return [
            'date' => 'date',
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
}
