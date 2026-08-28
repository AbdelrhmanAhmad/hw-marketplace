<?php

namespace App\Models\Builders;

use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * AD-001 Hardening — راجع docs/audit-log-integrity-hardening.md.
 *
 * يرفض أي Mass Update/Delete عبر Eloquent (AuditLog::query()->delete()،
 * AuditLog::where(...)->update([...])، إلخ) — مسارات لا تمر بدوال الـInstance
 * المحمية أصلًا بـApp\Models\AuditLog. طبقة دفاع ثانية، لا تُغني عن Trigger
 * قاعدة البيانات (الطبقة الأولى، تغطي أيضًا DB::table() الخام).
 */
class AppendOnlyBuilder extends Builder
{
    public function update(array $values): int
    {
        throw new LogicException('audit_logs جدول Append-only — لا تعديل جماعي مسموح بأي حال (AD-001).');
    }

    public function delete(): mixed
    {
        throw new LogicException('audit_logs جدول Append-only — لا حذف جماعي مسموح بأي حال (AD-001).');
    }
}
