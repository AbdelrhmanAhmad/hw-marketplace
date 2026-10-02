<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * بوابة التقنية — كتالوج خدمات تقنية (تصميم مواقع، تطبيقات، استضافة...)
 * تقدّمها المنصة نفسها (مزوّد واحد، بعكس مجتمع الخدمات متعدد المزوّدين).
 * يديره فريق المنصة حصرًا عبر Filament — لا نشر ذاتي من المستخدمين.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tech_services', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description');
            $table->string('category')->nullable(); // مثال: تطوير مواقع، تطبيقات جوال، استضافة...
            $table->string('price_note')->nullable(); // نص حر مثل "يبدأ من 3000 ريال" — لا بوابة دفع بعد
            $table->string('icon')->nullable();
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tech_services');
    }
};
