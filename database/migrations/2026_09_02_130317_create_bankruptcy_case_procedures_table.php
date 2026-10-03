<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bankruptcy_case_procedures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bankruptcy_case_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('pending'); // pending | in_progress | completed
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['bankruptcy_case_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bankruptcy_case_procedures');
    }
};
