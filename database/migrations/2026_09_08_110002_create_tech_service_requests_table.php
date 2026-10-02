<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * بوابة التقنية — طلب واحد يجمع عدة خدمات (سلة) أرسلها عميل محتمل (مكتب
 * محاماة أو مهني). user_id تُملأ لو كان مُسجِّل دخول، تبقى null لأي زائر
 * ضيف (نفس منطق service_listing_inquiries العام).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tech_service_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default('new'); // new|contacted|closed
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tech_service_requests');
    }
};
