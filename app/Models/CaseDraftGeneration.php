<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * محرك مسودة القضية الذكي — سجل توليد واحد، غير قابل للتعديل بعد الإنشاء
 * (CaseDraftService::generateDraft هي نقطة الإنشاء الوحيدة — لا update/delete
 * مكشوفة لأي Controller). راجع docs/marketplace-architecture-blueprint.md §7.
 */
#[Fillable([
    'bankruptcy_case_id', 'requested_by_user_id', 'organization_id', 'status', 'model',
    'prompt_version', 'content', 'input_tokens', 'output_tokens', 'estimated_cost_usd', 'error_message',
])]
class CaseDraftGeneration extends Model
{
    public function bankruptcyCase(): BelongsTo
    {
        return $this->belongsTo(BankruptcyCase::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
