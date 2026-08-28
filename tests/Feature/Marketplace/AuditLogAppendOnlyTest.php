<?php

namespace Tests\Feature\Marketplace;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

/**
 * AD-001 Hardening. راجع docs/audit-log-integrity-hardening.md (Attack/Bypass
 * Matrix + خطة الاختبارات). كل اختبار يثبت أمرين معًا: (أ) العملية تفشل،
 * (ب) البيانات لم تتغيّر إطلاقًا — لا يكفي إثبات Exception وحدها.
 *
 * وُلدت هذي الوثيقة من حادثة حقيقية: AuditLog::query()->delete() حذف 30 سجلًا
 * تاريخيًا حقيقيًا من قاعدة التطوير قبل وجود هذي الحماية. راجع
 * docs/audit-log-integrity-incident-report.md.
 */
class AuditLogAppendOnlyTest extends TestCase
{
    use RefreshDatabase;

    private function seedLog(): AuditLog
    {
        $user = User::factory()->create();

        return AuditLog::create([
            'organization_id' => null,
            'actor_user_id' => $user->id,
            'event' => 'subscription_created',
            'subject_type' => 'App\\Models\\Subscription',
            'subject_id' => 1,
            'metadata' => null,
        ]);
    }

    /** #1 — Instance delete() → MUST FAIL. */
    public function test_instance_delete_is_blocked(): void
    {
        $log = $this->seedLog();

        $this->expectException(LogicException::class);

        try {
            $log->delete();
        } finally {
            $this->assertDatabaseHas('audit_logs', ['id' => $log->id]);
            $this->assertSame(1, AuditLog::count());
        }
    }

    /** #2 — Instance update() → MUST FAIL. */
    public function test_instance_update_is_blocked(): void
    {
        $log = $this->seedLog();

        $this->expectException(LogicException::class);

        try {
            $log->update(['event' => 'tampered']);
        } finally {
            $this->assertSame('subscription_created', $log->fresh()->event);
        }
    }

    /** #3 — Query Builder delete() عبر Eloquent (AuditLog::where(...)->delete()) → MUST FAIL. */
    public function test_eloquent_query_builder_mass_delete_is_blocked(): void
    {
        $log = $this->seedLog();

        $this->expectException(LogicException::class);

        try {
            AuditLog::where('id', $log->id)->delete();
        } finally {
            $this->assertSame(1, AuditLog::count());
        }
    }

    /** #3b — AuditLog::query()->delete() تحديدًا (نفس المسار اللي سبَّب الحادثة). */
    public function test_query_delete_on_full_table_is_blocked(): void
    {
        $this->seedLog();
        $this->seedLog();
        $countBefore = AuditLog::count();

        $this->expectException(LogicException::class);

        try {
            AuditLog::query()->delete();
        } finally {
            $this->assertSame($countBefore, AuditLog::count());
        }
    }

    /** #4 — Query Builder update() عبر Eloquent → MUST FAIL. */
    public function test_eloquent_query_builder_mass_update_is_blocked(): void
    {
        $log = $this->seedLog();

        $this->expectException(LogicException::class);

        try {
            AuditLog::where('id', $log->id)->update(['event' => 'tampered']);
        } finally {
            $this->assertSame('subscription_created', $log->fresh()->event);
        }
    }

    /** #5 — AuditLog::destroy($id) (Bulk shortcut) → MUST FAIL، صفر تغيير. */
    public function test_destroy_shortcut_is_blocked(): void
    {
        $log = $this->seedLog();

        try {
            AuditLog::destroy($log->id);
        } catch (\Throwable $e) {
            // مقبول أي نوع استثناء هنا (LogicException من Instance، أو من AppendOnlyBuilder
            // حسب مسار Laravel الداخلي لـdestroy()) — المهم إثبات عدم الحذف الفعلي.
        }

        $this->assertDatabaseHas('audit_logs', ['id' => $log->id]);
        $this->assertSame(1, AuditLog::count());
    }

    /**
     * #6 — DB::table('audit_logs')->delete() — تجاوز Eloquent بالكامل.
     * الاختبار الأهم: يثبت الحماية بمستوى قاعدة البيانات (Trigger)، لا PHP.
     */
    public function test_raw_query_builder_delete_bypassing_eloquent_is_blocked(): void
    {
        $log = $this->seedLog();

        $this->expectException(QueryException::class);

        try {
            DB::table('audit_logs')->where('id', $log->id)->delete();
        } finally {
            $this->assertSame(1, DB::table('audit_logs')->count());
        }
    }

    /** #7 — DB::table('audit_logs')->update([...]) الخام → MUST FAIL بنفس الطريقة. */
    public function test_raw_query_builder_update_bypassing_eloquent_is_blocked(): void
    {
        $log = $this->seedLog();

        $this->expectException(QueryException::class);

        try {
            DB::table('audit_logs')->where('id', $log->id)->update(['event' => 'tampered']);
        } finally {
            $this->assertSame('subscription_created', DB::table('audit_logs')->find($log->id)->event);
        }
    }

    /** #8 — SQL خام مباشر (DB::statement) — يثبت الحماية مستقلة حتى عن Query Builder نفسه. */
    public function test_raw_sql_statement_delete_is_blocked(): void
    {
        $log = $this->seedLog();

        $this->expectException(QueryException::class);

        try {
            DB::statement('DELETE FROM audit_logs WHERE id = ?', [$log->id]);
        } finally {
            $this->assertSame(1, DB::table('audit_logs')->count());
        }
    }

    /** #8b — TRUNCATE عبر SQLite (يُنفَّذ كـDELETE FROM داخليًا بهذا المحرك — محمي أيضًا). */
    public function test_truncate_is_blocked_on_sqlite(): void
    {
        $this->seedLog();
        $this->seedLog();

        $this->expectException(\Throwable::class);

        try {
            DB::table('audit_logs')->truncate();
        } finally {
            $this->assertSame(2, DB::table('audit_logs')->count());
        }
    }

    /** #9 — لا مسار Filament/Admin لـ audit_logs إطلاقًا (فحص ثابت، لا سلوك). */
    public function test_no_filament_resource_exists_for_audit_logs(): void
    {
        $this->assertFileDoesNotExist(app_path('Filament/Resources/AuditLogResource.php'));
    }

    /** #10 — Regression: الاستخدام الشرعي الوحيد (create) ما زال يعمل بلا أي تأثير من الحماية. */
    public function test_legitimate_create_still_works(): void
    {
        $log = $this->seedLog();

        $this->assertDatabaseHas('audit_logs', [
            'id' => $log->id,
            'event' => 'subscription_created',
        ]);
    }
}
