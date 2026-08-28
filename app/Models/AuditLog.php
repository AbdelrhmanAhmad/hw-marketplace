<?php

namespace App\Models;

use App\Models\Builders\AppendOnlyBuilder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * AD-001 — Append-only بالتصميم، لا اتفاق إجرائي فقط. لا UI، لا بحث،
 * لا Analytics، لا Export — لا شيء أبعد من الكتابة والقراءة اليدوية.
 * يُكتَب من App\Services\SubscriptionService، OrganizationSubscriptionService،
 * وSeatService — الثلاثة فقط (مؤكَّد بـgrep شامل).
 *
 * الحماية بثلاث طبقات مستقلة (راجع docs/audit-log-integrity-hardening.md):
 * (١) Instance update()/delete() أدناه، (٢) AppendOnlyBuilder لأي Mass
 * Update/Delete عبر Eloquent، (٣) DB Trigger (Migration منفصلة) يرفض أي
 * UPDATE/DELETE بمستوى قاعدة البيانات نفسها بغض النظر عن المسار — الضمان
 * الوحيد الشامل حتى لـDB::table('audit_logs') الخام.
 */
#[Fillable(['organization_id', 'actor_user_id', 'event', 'subject_type', 'subject_id', 'metadata'])]
class AuditLog extends Model
{
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function newEloquentBuilder($query): AppendOnlyBuilder
    {
        return new AppendOnlyBuilder($query);
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new LogicException('audit_logs جدول Append-only — لا تعديل مسموح بأي حال (AD-001/BR-014).');
    }

    public function delete(): ?bool
    {
        throw new LogicException('audit_logs جدول Append-only — لا حذف مسموح بأي حال (AD-001/BR-014).');
    }
}
