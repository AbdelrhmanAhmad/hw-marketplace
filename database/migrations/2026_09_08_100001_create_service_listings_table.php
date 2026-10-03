<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مجتمع الخدمات — دليل عام: محامون/مختصون ينشرون خدماتهم ليراها أي زائر
 * (بلا تسجيل دخول، مطابق لبوابة المقالات). لا بوابة موافقة إدارية — النشر
 * فوري، المالك وحده يقدر يوقف ظهور إعلانه. النشر نفسه يتطلب حساب مسجَّل
 * (marketplace.entitled:community على مستوى لوحة "إعلاناتي" فقط).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('category'); // قانوني|مالي|محاسبي|عام
            $table->string('title');
            $table->text('description');
            $table->string('contact_method'); // phone|email|whatsapp
            $table->string('contact_value');
            $table->string('status')->default('open'); // open|closed
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_listings');
    }
};
