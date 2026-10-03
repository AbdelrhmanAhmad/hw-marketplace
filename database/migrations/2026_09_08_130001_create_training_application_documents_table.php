<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * بوابة التدريب التعاوني — مستندات التقديم (سيرة ذاتية، إفادة قيد...).
 * قرص local خاص (لا public) — نفس نمط bankruptcy_case_documents تمامًا؛
 * هذي مستندات شخصية حسّاسة (هوية، كشف درجات)، لا تُعامَل معاملة صورة
 * غلاف مقال عامة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_application_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // cv|enrollment_letter|transcript|national_id
            $table->string('original_filename');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamps();

            $table->unique(['training_application_id', 'type'], 'training_application_documents_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_application_documents');
    }
};
