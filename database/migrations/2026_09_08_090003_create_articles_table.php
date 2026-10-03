<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إعادة نشر مقال منشور فعليًا (تعديل) تُعيده تلقائيًا لـ pending_review —
 * لا نظام إصدارات مزدوج (حيّ + مسودة) — أبسط، ويطابق قاعدة "كل تغيير
 * يحتاج مراجعة" حرفيًا. المحتوى نص عادي (لا Markdown/HTML) — لا خطر XSS
 * من محتوى يكتبه مستخدمون خارجيون.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_author_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->string('cover_image_path')->nullable();
            $table->string('status')->default('draft'); // draft|pending_review|published|rejected
            $table->text('rejection_reason')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index('article_author_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
