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
        Schema::create('application_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('entry_route')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_details');
    }
};
