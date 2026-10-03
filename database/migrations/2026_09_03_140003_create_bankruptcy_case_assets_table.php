<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bankruptcy_case_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bankruptcy_case_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('value', 14, 2);
            $table->string('location');
            $table->text('description')->nullable();
            $table->foreignId('added_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('bankruptcy_case_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bankruptcy_case_assets');
    }
};
