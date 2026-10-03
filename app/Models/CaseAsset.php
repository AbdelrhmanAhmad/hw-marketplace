<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['bankruptcy_case_id', 'name', 'value', 'location', 'description', 'added_by_user_id'])]
class CaseAsset extends Model
{
    protected $table = 'bankruptcy_case_assets';

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
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
