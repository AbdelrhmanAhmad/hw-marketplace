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
        Schema::create('law_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('law_entry_id')->constrained()->cascadeOnDelete();
            $table->string('article_number');
            $table->text('content');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('law_articles');
    }
};
