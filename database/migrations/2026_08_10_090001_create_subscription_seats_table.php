<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * AD-008 — كيان مستقل عن access_assignments. Seat = تخصيص إداري،
     * AccessAssignment = صلاحية استخدام فعلية. UNIQUE هنا خط دفاع إضافي
     * ضد Concurrency (AD-003/BR-2B-07)، ليس بديلًا عن lockForUpdate.
     */
    public function up(): void
    {
        Schema::create('subscription_seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('assigned');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->unique(['subscription_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_seats');
    }
};
