<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * محرك مسودة القضية الذكي — سجل تدقيق غير قابل للتعديل لكل توليد (راجع
 * docs/marketplace-architecture-blueprint.md §7: Model Selection، Prompt
 * Versioning، Usage & Cost Tracking، Audit Logs — إلزامية من التصميم لا
 * إضافة لاحقة). لا Route/Controller يعدّل صفًا هنا بعد إنشائه — Create فقط.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_draft_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bankruptcy_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            // لقطة وقت التوليد — Tenant Isolation/Data Provenance (لا تعتمد
            // على قيمة القضية الحالية لو تغيّرت لاحقًا، السجل التدقيقي ثابت).
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status'); // completed|failed
            $table->string('model');
            $table->string('prompt_version');
            $table->longText('content')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->decimal('estimated_cost_usd', 10, 4)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['bankruptcy_case_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_draft_generations');
    }
};
