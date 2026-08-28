<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * AD-001 — Audit Minimal: append-only، خمسة أحداث فقط قابلة للحدوث فعليًا
     * بـPhase 1b (Subscription Created/Activated/Cancelled، Access Granted/Revoked).
     * لا عمود updated_at عمدًا — غياب العمود نفسه قيد بنيوي ضد التعديل، بالإضافة
     * لإلغاء update()/delete() على مستوى الـModel (راجع App\Models\AuditLog).
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('event');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index('actor_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
