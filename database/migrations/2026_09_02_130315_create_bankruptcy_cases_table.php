<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إفلاس تك — أول تطبيق حقيقي غير بوابة معرفة. مسار مزدوج (شخصي/مؤسسي) نفس
 * نمط Subscription: organization_id=null يعني قضية شخصية. لا Authorization
 * هنا — يُفرَض بالكامل عبر BankruptcyCasePolicy عند نقطة الفعل (AD-012).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bankruptcy_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_number')->nullable()->unique();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->date('opened_at')->nullable();
            $table->date('closed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['created_by_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bankruptcy_cases');
    }
};
