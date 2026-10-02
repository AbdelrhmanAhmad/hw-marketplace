<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Models\Article;
use App\Models\ArticleAuthor;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * بوابة المقالات — نقطة الدخول الوحيدة لكل Mutation (BR-013، نفس نمط
 * BankruptcyCaseService). موافقة/رفض المؤلفين والمقالات تُستدعى حصرًا من
 * Filament Actions — الحارس الوحيد `is_platform_staff` (canAccessPanel)،
 * بلا Policy إضافية، مطابقًا لبقية موارد Filament الحالية بهذا المشروع.
 */
class ArticleService
{
    private const array VALID_AUTHOR_STATUSES = ['pending', 'approved', 'rejected'];

    private const array VALID_ARTICLE_STATUSES = ['draft', 'pending_review', 'published', 'rejected'];

    /**
     * مؤلف مرفوض يقدر يعيد التقديم (يحدّث نفس السجل، لا يُحظَر للأبد) — طلب
     * قائم فعلًا (pending) أو مُوافَق عليه مسبقًا (approved) فقط يُرفَضان.
     */
    public function requestAuthorship(User $actor, string $bio, string $expertise): ArticleAuthor
    {
        $existing = ArticleAuthor::where('user_id', $actor->id)->first();

        if ($existing && in_array($existing->status, ['pending', 'approved'], true)) {
            throw new InvalidArgumentException('لديك طلب تأليف مسبق بالفعل.');
        }

        if (trim($bio) === '' || trim($expertise) === '') {
            throw new InvalidArgumentException('النبذة والتخصص مطلوبان.');
        }

        return DB::transaction(function () use ($actor, $existing, $bio, $expertise) {
            if ($existing) {
                $existing->update([
                    'bio' => $bio, 'expertise' => $expertise, 'status' => 'pending',
                    'reviewed_by_user_id' => null, 'reviewed_at' => null, 'rejection_reason' => null,
                ]);
                $author = $existing;
            } else {
                $author = ArticleAuthor::create([
                    'user_id' => $actor->id,
                    'bio' => $bio,
                    'expertise' => $expertise,
                    'status' => 'pending',
                ]);
            }

            $this->log($actor, AuditEvent::ArticleAuthorRequested, $author, ['user_id' => $actor->id]);

            return $author;
        });
    }

    public function approveAuthor(User $staff, ArticleAuthor $author): void
    {
        DB::transaction(function () use ($staff, $author) {
            $author->update(['status' => 'approved', 'reviewed_by_user_id' => $staff->id, 'reviewed_at' => now(), 'rejection_reason' => null]);
            $this->log($staff, AuditEvent::ArticleAuthorApproved, $author, ['user_id' => $author->user_id]);
        });
    }

    public function rejectAuthor(User $staff, ArticleAuthor $author, string $reason): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('سبب الرفض مطلوب.');
        }

        DB::transaction(function () use ($staff, $author, $reason) {
            $author->update(['status' => 'rejected', 'reviewed_by_user_id' => $staff->id, 'reviewed_at' => now(), 'rejection_reason' => $reason]);
            $this->log($staff, AuditEvent::ArticleAuthorRejected, $author, ['user_id' => $author->user_id, 'reason' => $reason]);
        });
    }

    public function createDraft(User $actor, array $data): Article
    {
        Gate::forUser($actor)->authorize('create', Article::class);
        $author = ArticleAuthor::where('user_id', $actor->id)->firstOrFail();

        if (trim($data['title'] ?? '') === '' || trim($data['body'] ?? '') === '') {
            throw new InvalidArgumentException('العنوان والمحتوى مطلوبان.');
        }

        return DB::transaction(function () use ($actor, $author, $data) {
            $article = Article::create([
                'article_author_id' => $author->id,
                'article_category_id' => $data['article_category_id'] ?? null,
                'title' => $data['title'],
                'slug' => $this->uniqueSlug($data['title']),
                'excerpt' => $data['excerpt'] ?? null,
                'body' => $data['body'],
                'cover_image_path' => $data['cover_image_path'] ?? null,
                'status' => 'draft',
            ]);

            $this->log($actor, AuditEvent::ArticleCreated, $article, ['title' => $article->title]);

            return $article;
        });
    }

    /**
     * تعديل مقال منشور فعليًا يُعيده تلقائيًا لـ pending_review (قرار #2
     * بالخطة) — لا نظام إصدارات مزدوج، أي تغيير على محتوى حيّ يحتاج مراجعة
     * جديدة، يطابق "كل مقال يحتاج مراجعة قبل النشر" حرفيًا.
     */
    public function updateDraft(User $actor, Article $article, array $data): void
    {
        Gate::forUser($actor)->authorize('update', $article);

        if (trim($data['title'] ?? $article->title) === '' || trim($data['body'] ?? $article->body) === '') {
            throw new InvalidArgumentException('العنوان والمحتوى مطلوبان.');
        }

        DB::transaction(function () use ($actor, $article, $data) {
            $wasPublished = $article->status === 'published';

            $article->fill([
                'article_category_id' => $data['article_category_id'] ?? $article->article_category_id,
                'title' => $data['title'] ?? $article->title,
                'excerpt' => $data['excerpt'] ?? $article->excerpt,
                'body' => $data['body'] ?? $article->body,
                'cover_image_path' => $data['cover_image_path'] ?? $article->cover_image_path,
            ]);

            if ($wasPublished) {
                $article->status = 'pending_review';
                $article->published_at = null;
            }

            $article->save();

            if ($wasPublished) {
                $this->log($actor, AuditEvent::ArticleSubmittedForReview, $article, ['reason' => 'edited_after_publish']);
            }
        });
    }

    public function submitForReview(User $actor, Article $article): void
    {
        Gate::forUser($actor)->authorize('submit', $article);

        if (! in_array($article->status, ['draft', 'rejected'], true)) {
            throw new InvalidArgumentException('لا يمكن إرسال هذا المقال للمراجعة بحالته الحالية.');
        }

        DB::transaction(function () use ($actor, $article) {
            $article->update(['status' => 'pending_review', 'rejection_reason' => null]);
            $this->log($actor, AuditEvent::ArticleSubmittedForReview, $article, ['case' => 'manual_submit']);
        });
    }

    public function approveArticle(User $staff, Article $article): void
    {
        DB::transaction(function () use ($staff, $article) {
            $article->update(['status' => 'published', 'published_at' => now(), 'rejection_reason' => null]);
            $this->log($staff, AuditEvent::ArticlePublished, $article, ['title' => $article->title]);
        });
    }

    public function rejectArticle(User $staff, Article $article, string $reason): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('سبب الرفض مطلوب.');
        }

        DB::transaction(function () use ($staff, $article, $reason) {
            $article->update(['status' => 'rejected', 'published_at' => null, 'rejection_reason' => $reason]);
            $this->log($staff, AuditEvent::ArticleRejected, $article, ['reason' => $reason]);
        });
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'article';
        $slug = $base;
        $suffix = 1;

        while (Article::where('slug', $slug)->exists()) {
            $suffix++;
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }

    private function log(User $actor, AuditEvent $event, $subject, array $metadata = []): void
    {
        AuditLog::create([
            'organization_id' => null,
            'actor_user_id' => $actor->id,
            'event' => $event->value,
            'subject_type' => $subject::class,
            'subject_id' => $subject->id,
            'metadata' => $metadata,
        ]);
    }
}
