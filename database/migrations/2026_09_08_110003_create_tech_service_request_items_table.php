<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** بوابة التقنية — بنود سلة الطلب (أي الخدمات المطلوبة ضمن طلب واحد). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tech_service_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tech_service_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tech_service_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tech_service_request_id', 'tech_service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tech_service_request_items');
    }
};
