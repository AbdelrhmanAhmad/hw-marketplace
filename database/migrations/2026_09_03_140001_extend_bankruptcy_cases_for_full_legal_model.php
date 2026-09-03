<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * إفلاس تك — المرحلة 1 (النموذج القانوني الكامل، منقول من hw-eflas).
 *
 * تنبيه تسمية مهم: `case_number` (عمود موجود مسبقًا) رقم تتبّع داخلي يُولَّد
 * تلقائيًا (`BK-YYYY-00001`) عند الإنشاء — مختلف تمامًا عن `court_case_number`
 * الجديد هنا، وهو رقم القضية الصادر من المحكمة فعليًا، يُدخَل يدويًا بعد
 * التقديم. لا تخلط بينهما.
 *
 * `total_debts`/`total_assets` عمدًا غير موجودين هنا — يُحسَبان حيًا من
 * `sum(creditors.amount)`/`sum(assets.value)` (accessors على BankruptcyCase)
 * بدل تخزين قيمة مكرَّرة قابلة للـ Drift.
 *
 * كل عمود جديد nullable (القضايا الحالية تُملأ تدريجيًا، لا قيمة افتراضية
 * مصطنعة) إلا حيث القيمة الافتراضية منطقية (القوائم التنظيمية/الأعلام).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bankruptcy_cases', function (Blueprint $table) {
            $table->string('court_case_number')->nullable()->after('case_number');

            $table->string('debtor_name')->nullable();
            $table->string('legal_form')->nullable();
            $table->string('cr_number', 10)->nullable();
            $table->string('cr_city')->nullable();
            $table->string('court_city')->nullable();
            $table->string('representative_name')->nullable();
            $table->string('representative_title')->nullable();
            $table->string('representative_id')->nullable();
            $table->string('attorney_name')->nullable();
            $table->string('attorney_license')->nullable();
            $table->date('submission_date')->nullable();
            $table->string('trustee_name')->nullable();

            // إجابات معالج التشخيص العشرة — نفس Tokens hw-eflas حرفيًا
            // (yes/no أو قيم محدَّدة) كي يبقى BankruptcyRecommendationEngine
            // نسخة طبق الأصل من recommendation.ts بلا أي تحويل وسيط.
            $table->string('is_establishment')->nullable();
            $table->string('is_active')->nullable();
            $table->string('has_assets')->nullable();
            $table->string('assets_cover_expenses')->nullable();
            $table->string('insolvency_status')->nullable();
            $table->string('financial_statements_available')->nullable();
            $table->string('financial_transactions_available')->nullable();
            $table->string('creditors_notified')->nullable();
            $table->string('operated_twelve_months')->nullable();
            $table->string('previous_settlement')->nullable();

            $table->string('zatca_file_number')->nullable();
            $table->json('zatca_checklist')->nullable();
            $table->string('gosi_file_number')->nullable();
            $table->json('gosi_checklist')->nullable();
            $table->json('hr_checklist')->nullable();
            $table->boolean('commerce_cr_cancellation_requested')->default(false);
            $table->boolean('sama_notified')->default(false);
        });

        // هجرة بيانات — لا Schema فقط: القيم الأربع القديمة (draft/active/
        // under_procedure/closed) تُستبدَل بخمس قيم أدق تطابق واقع التقاضي
        // (draft/preparing/submitted/decided/closed) بلا فقد أي قضية موجودة.
        DB::table('bankruptcy_cases')->where('status', 'active')->update(['status' => 'preparing']);
        DB::table('bankruptcy_cases')->where('status', 'under_procedure')->update(['status' => 'submitted']);
    }

    public function down(): void
    {
        // عكس هجرة البيانات قبل إسقاط الأعمدة — يحفظ إمكانية التراجع الكامل.
        DB::table('bankruptcy_cases')->where('status', 'preparing')->update(['status' => 'active']);
        DB::table('bankruptcy_cases')->where('status', 'submitted')->update(['status' => 'under_procedure']);
        // ملاحظة: 'decided' لا مقابل لها بالمخطط القديم — تُترَك كما هي عند
        // rollback (كانت ستُصبح قيمة غير معروفة أصلًا لو أُدخلت بعد الترقية).

        Schema::table('bankruptcy_cases', function (Blueprint $table) {
            $table->dropColumn([
                'court_case_number', 'debtor_name', 'legal_form', 'cr_number', 'cr_city', 'court_city',
                'representative_name', 'representative_title', 'representative_id', 'attorney_name', 'attorney_license',
                'submission_date', 'trustee_name',
                'is_establishment', 'is_active', 'has_assets', 'assets_cover_expenses', 'insolvency_status',
                'financial_statements_available', 'financial_transactions_available', 'creditors_notified',
                'operated_twelve_months', 'previous_settlement',
                'zatca_file_number', 'zatca_checklist', 'gosi_file_number', 'gosi_checklist', 'hr_checklist',
                'commerce_cr_cancellation_requested', 'sama_notified',
            ]);
        });
    }
};
