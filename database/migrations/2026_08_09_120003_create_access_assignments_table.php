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
        Schema::create('access_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('active');
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'subscription_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_assignments');
    }
};
