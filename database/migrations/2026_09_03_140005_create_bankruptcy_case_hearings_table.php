<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bankruptcy_case_hearings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bankruptcy_case_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('type'); // جلسة_أولى|جلسة_موضوع|جلسة_قرار|أخرى
            $table->text('notes')->nullable();
            $table->text('result')->nullable();
            $table->foreignId('added_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('bankruptcy_case_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bankruptcy_case_hearings');
    }
};
