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
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_item_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('seat_limit')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('billing_cycle')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
