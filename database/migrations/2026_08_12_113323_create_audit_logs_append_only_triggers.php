<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AD-001 Hardening — راجع docs/audit-log-integrity-hardening.md.
 *
 * حماية بمستوى قاعدة البيانات نفسها، مستقلة تمامًا عن Eloquent/PHP — تمنع
 * UPDATE/DELETE على audit_logs بغض النظر عن المسار (Instance، Query Builder،
 * Raw SQL، حتى عميل SQLite مباشر). الطبقة الوحيدة القادرة فعليًا على تحقيق
 * "حتى لو استخدم مطور هذا الأسلوب بالخطأ، يفشل التنفيذ" بلا استثناء.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER prevent_audit_logs_update
            BEFORE UPDATE ON audit_logs
            BEGIN
                SELECT RAISE(ABORT, 'audit_logs is append-only — UPDATE rejected at database level (AD-001)');
            END;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER prevent_audit_logs_delete
            BEFORE DELETE ON audit_logs
            BEGIN
                SELECT RAISE(ABORT, 'audit_logs is append-only — DELETE rejected at database level (AD-001)');
            END;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS prevent_audit_logs_update;');
        DB::unprepared('DROP TRIGGER IF EXISTS prevent_audit_logs_delete;');
    }
};
