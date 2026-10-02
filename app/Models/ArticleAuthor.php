<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * بوابة المقالات — ملف/طلب تأليف. لا Authorization هنا — ArticleService
 * وحدها تقرر عبر Gate/فحص الحالة (نفس نمط BankruptcyCase). واحد لكل User
 * (unique بـuser_id بالـmigration).
 */
#[Fillable(['user_id', 'bio', 'expertise', 'status', 'reviewed_by_user_id', 'reviewed_at', 'rejection_reason'])]
class ArticleAuthor extends Model
{
    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
