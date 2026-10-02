<?php

namespace Tests\Feature\Articles;

use App\Enums\AuditEvent;
use App\Models\Article;
use App\Models\ArticleAuthor;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\ArticleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/** بوابة المقالات — دورة الحياة الكاملة: طلب تأليف → موافقة → مقال → مراجعة → نشر. */
class ArticleServiceTest extends TestCase
{
    use RefreshDatabase;

    private function approvedAuthor(): array
    {
        $user = User::factory()->create();
        $staff = User::factory()->create(['is_platform_staff' => true]);
        $service = app(ArticleService::class);
        $author = $service->requestAuthorship($user, 'نبذة كافية', 'محامٍ');
        $service->approveAuthor($staff, $author);

        return [$user, $author->fresh()];
    }

    // --- طلب التأليف ---

    public function test_user_can_request_authorship(): void
    {
        $user = User::factory()->create();

        $author = app(ArticleService::class)->requestAuthorship($user, 'نبذة', 'محاسب قانوني');

        $this->assertSame('pending', $author->status);
        $this->assertTrue(AuditLog::where('event', AuditEvent::ArticleAuthorRequested->value)->exists());
    }

    public function test_cannot_request_authorship_twice_while_pending(): void
    {
        $user = User::factory()->create();
        app(ArticleService::class)->requestAuthorship($user, 'نبذة', 'محامٍ');

        $this->expectException(InvalidArgumentException::class);
        app(ArticleService::class)->requestAuthorship($user, 'نبذة أخرى', 'محامٍ آخر');
    }

    public function test_rejected_author_can_reapply(): void
    {
        $user = User::factory()->create();
        $staff = User::factory()->create(['is_platform_staff' => true]);
        $service = app(ArticleService::class);
        $author = $service->requestAuthorship($user, 'نبذة', 'محامٍ');
        $service->rejectAuthor($staff, $author, 'نبذة غير كافية');

        $reapplied = $service->requestAuthorship($user, 'نبذة مفصَّلة أكثر', 'محامٍ متخصص');

        $this->assertSame('pending', $reapplied->status);
        $this->assertSame($author->id, $reapplied->id, 'يجب تحديث نفس السجل لا إنشاء آخر');
    }

    public function test_approved_author_cannot_request_again(): void
    {
        [$user] = $this->approvedAuthor();

        $this->expectException(InvalidArgumentException::class);
        app(ArticleService::class)->requestAuthorship($user, 'نبذة', 'محامٍ');
    }

    // --- إنشاء وتعديل المقالات ---

    public function test_unapproved_user_cannot_create_article(): void
    {
        $user = User::factory()->create();

        $this->expectException(AuthorizationException::class);
        app(ArticleService::class)->createDraft($user, ['title' => 'عنوان', 'body' => 'محتوى']);
    }

    public function test_approved_author_can_create_and_submit_article(): void
    {
        [$user] = $this->approvedAuthor();

        $article = app(ArticleService::class)->createDraft($user, ['title' => 'مقال تجريبي', 'body' => 'محتوى المقال']);
        $this->assertSame('draft', $article->status);
        $this->assertNotEmpty($article->slug);

        app(ArticleService::class)->submitForReview($user, $article);
        $this->assertSame('pending_review', $article->fresh()->status);
    }

    public function test_author_cannot_edit_another_authors_article(): void
    {
        [$userA] = $this->approvedAuthor();
        [$userB] = $this->approvedAuthor();
        $article = app(ArticleService::class)->createDraft($userA, ['title' => 'مقال أ', 'body' => 'محتوى']);

        $this->expectException(AuthorizationException::class);
        app(ArticleService::class)->updateDraft($userB, $article, ['title' => 'محاولة تعديل']);
    }

    public function test_editing_a_published_article_reverts_it_to_pending_review(): void
    {
        [$user, $author] = $this->approvedAuthor();
        $staff = User::factory()->create(['is_platform_staff' => true]);
        $service = app(ArticleService::class);

        $article = $service->createDraft($user, ['title' => 'مقال', 'body' => 'محتوى أولي']);
        $service->submitForReview($user, $article);
        $service->approveArticle($staff, $article);
        $this->assertSame('published', $article->fresh()->status);

        $service->updateDraft($user, $article, ['title' => 'مقال مُعدَّل', 'body' => 'محتوى مُعدَّل']);

        $fresh = $article->fresh();
        $this->assertSame('pending_review', $fresh->status);
        $this->assertNull($fresh->published_at);
    }

    public function test_duplicate_slugs_get_a_unique_suffix(): void
    {
        [$user] = $this->approvedAuthor();
        $service = app(ArticleService::class);

        $first = $service->createDraft($user, ['title' => 'نفس العنوان', 'body' => 'محتوى 1']);
        $second = $service->createDraft($user, ['title' => 'نفس العنوان', 'body' => 'محتوى 2']);

        $this->assertNotSame($first->slug, $second->slug);
    }

    // --- المراجعة (نفس نمط رفض بسبب) ---

    public function test_rejecting_an_article_requires_a_reason(): void
    {
        [$user] = $this->approvedAuthor();
        $staff = User::factory()->create(['is_platform_staff' => true]);
        $service = app(ArticleService::class);
        $article = $service->createDraft($user, ['title' => 'مقال', 'body' => 'محتوى']);
        $service->submitForReview($user, $article);

        $this->expectException(InvalidArgumentException::class);
        $service->rejectArticle($staff, $article, '');
    }

    public function test_approving_an_article_sets_published_at(): void
    {
        [$user] = $this->approvedAuthor();
        $staff = User::factory()->create(['is_platform_staff' => true]);
        $service = app(ArticleService::class);
        $article = $service->createDraft($user, ['title' => 'مقال', 'body' => 'محتوى']);
        $service->submitForReview($user, $article);

        $service->approveArticle($staff, $article);

        $fresh = $article->fresh();
        $this->assertSame('published', $fresh->status);
        $this->assertNotNull($fresh->published_at);
        $this->assertTrue(AuditLog::where('event', AuditEvent::ArticlePublished->value)->exists());
    }
}
