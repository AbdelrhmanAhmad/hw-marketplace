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
        Schema::create('marketplace_items', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('type')->default('application');
            $table->foreignId('partner_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('marketplace_categories')->nullOnDelete();
            $table->string('name');
            $table->string('tagline');
            $table->text('description');
            $table->string('icon');
            $table->string('status')->default('published');
            $table->string('billing_model')->default('user_only');
            $table->string('pricing_model')->nullable();
            $table->json('compatibility')->nullable();
            $table->string('version')->default('1.0');
            $table->timestamps();

            $table->index(['type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_items');
    }
};
