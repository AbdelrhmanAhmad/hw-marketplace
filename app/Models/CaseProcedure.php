<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['bankruptcy_case_id', 'title', 'description', 'status', 'due_date', 'completed_at', 'created_by_user_id'])]
class CaseProcedure extends Model
{
    protected $table = 'bankruptcy_case_procedures';

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function bankruptcyCase(): BelongsTo
    {
        return $this->belongsTo(BankruptcyCase::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'in_progress' => 'قيد التنفيذ',
            'completed' => 'مكتمل',
            default => 'قيد الانتظار',
        };
    }
}
