<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مجتمع الخدمات — استفسار زائر عن إعلان (الزائر غالبًا "عامة الناس"، ليس
 * بالضرورة عضوًا مسجَّلًا — نفس منطق ServiceInterest العام). user_id تُملأ
 * تلقائيًا لو كان مُسجِّل دخول، تبقى null لأي زائر ضيف — الاسم/البريد
 * يُلتقَطان دائمًا لأن لا حساب بالضرورة يحمل بيانات موثوقة لهما.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_listing_inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_listing_inquiries');
    }
};
