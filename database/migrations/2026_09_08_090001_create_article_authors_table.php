<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * بوابة المقالات — ملف/طلب تأليف، واحد لكل User (unique). أول نموذج "طلب →
 * موافقة/رفض" بهذا المشروع — لا يوجد نمط سابق مشابه (تحقق شامل قبل التصميم).
 * الموافقة/الرفض تُدار حصرًا من Filament (is_platform_staff هو الحارس
 * الوحيد، بلا Policy إضافية — مطابقًا لبقية موارد Filament الحالية).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_authors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->text('bio');
            $table->string('expertise');
            $table->string('status')->default('pending'); // pending|approved|rejected
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_authors');
    }
};
