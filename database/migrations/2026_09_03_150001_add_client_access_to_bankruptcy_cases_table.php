<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إفلاس تك — المرحلة 2 (بوابة العميل الخارجية). `client_user_id` مصدر
 * الحقيقة الوحيد لـ"من هو عميل هذي القضية" — مستقل تمامًا عن Membership/
 * Organization (يعمل بالمسارين الشخصي والمؤسسي بلا تفريق). الإلغاء Soft
 * (`client_access_revoked_at`) لا حذف User — AuditLog.actor_user_id
 * restrictOnDelete يمنع حذف أي حساب أدّى فعلًا مُدقَّقًا (رفع مستند مثلًا).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bankruptcy_cases', function (Blueprint $table) {
            $table->foreignId('client_user_id')->nullable()->unique()->after('created_by_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('client_access_revoked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bankruptcy_cases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_user_id');
            $table->dropColumn('client_access_revoked_at');
        });
    }
};
