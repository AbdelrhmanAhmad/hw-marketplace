<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * بوابة التدريب التعاوني — مكاتب/شركات/محامون ينشرون فرص تدريب للطلاب.
 * عكس اتجاه مجتمع الخدمات: هنا المهني يطلب متدرّبًا (لا يعرض خدمة)، والطالب
 * هو من يتصفح. تصفّح عام بلا Auth، لكن التقديم يتطلب حساب مسجَّل (تحقّق
 * صريح من المستخدم). لا بوابة موافقة إدارية — النشر فوري، المالك يغلق فرصته.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('category'); // قانوني|مالي|محاسبي|عام
            $table->string('title');
            $table->text('description');
            $table->string('location')->nullable(); // مثال: الرياض، عن بُعد
            $table->string('duration')->nullable(); // مثال: 3 أشهر
            $table->string('status')->default('open'); // open|closed
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_opportunities');
    }
};
