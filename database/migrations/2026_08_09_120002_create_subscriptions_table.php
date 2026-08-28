<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * جدول جديد بمعزل تام عن app_subscriptions القديم (Core Platform Phase 1،
     * غير مُمَس) — Phase 1b تبني نظامًا موازيًا، لا تستبدل القديم. راجع
     * docs/phase-1b-completion-report.md لتوثيق التعايش المؤقت.
     *
     * subscriber_type مقيَّد بقيمتين فقط (AD-002) — 'user' فقط فعليًا بـPhase 1b،
     * الإنفاذ حاليًا على مستوى SubscriptionService (نقطة الدخول الوحيدة)،
     * لا CHECK على مستوى قاعدة البيانات بعد (لا حاجة فعلية قبل أول كتابة org).
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('subscriber_type');
            $table->unsignedBigInteger('subscriber_id');
            $table->foreignId('marketplace_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('active');
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamps();

            $table->index(['subscriber_type', 'subscriber_id']);
            $table->unique(['subscriber_type', 'subscriber_id', 'marketplace_item_id'], 'subscriptions_subscriber_item_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
