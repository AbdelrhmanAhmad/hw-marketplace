<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('law_entries', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('number')->nullable();
            $table->string('hijri_date')->nullable();
            $table->date('gregorian_date')->nullable();
            $table->enum('status', ['نافذ', 'معلق النفاذ', 'ملغى'])->default('نافذ');
            $table->string('issuing_authority')->nullable();
            $table->text('summary')->nullable();
            $table->string('source_url')->nullable();
            $table->string('external_id')->nullable()->comment('معرف للربط المستقبلي بمصدر API رسمي');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('law_entries');
    }
};
