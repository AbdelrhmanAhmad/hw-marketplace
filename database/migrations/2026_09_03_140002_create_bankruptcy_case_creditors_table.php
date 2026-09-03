<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إفلاس تك — سجل مالي مستقل عن CaseParty عمدًا (قرار #5 بخطة المرحلة 1):
 * دائن مالي بمبلغ/أولوية لا يتطلب وجود سجل CaseParty مطابق، ولا العكس.
 * الأولوية (priority) تتبع ترتيب المادة 52 النظامي — تُستخدَم مباشرة من
 * BankruptcyRecommendationEngine ومن ترتيب جدول الديون بالواجهة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bankruptcy_case_creditors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bankruptcy_case_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('amount', 14, 2);
            $table->string('priority'); // p1_expenses|p1_employees|p1_government|p2_secured|p3_unsecured|p4_deferred
            $table->string('type');
            $table->date('date');
            $table->string('contact')->nullable();
            $table->string('pledge_type')->nullable();
            $table->boolean('pledge_registered')->nullable();
            $table->foreignId('added_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['bankruptcy_case_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bankruptcy_case_creditors');
    }
};
