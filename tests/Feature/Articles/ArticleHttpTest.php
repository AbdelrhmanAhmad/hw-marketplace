<?php

namespace Tests\Feature\Articles;

use App\Models\Article;
use App\Models\ArticleAuthor;
use App\Models\User;
use App\Services\ArticleService;
use Database\Seeders\MarketplaceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** بوابة المقالات عبر HTTP — الصفحة العامة بلا Auth، ولوحة المؤلف خلف marketplace.entitled. */
class ArticleHttpTest extends TestCase
{
    use RefreshDatabase;

    private function publishedArticle(): Article
    {
        $user = User::factory()->create();
        $staff = User::factory()->create(['is_platform_staff' => true]);
        $service = app(ArticleService::class);
        $author = $service->requestAuthorship($user, 'نبذة', 'محامٍ');
        $service->approveAuthor($staff, $author);
        $article = $service->createDraft($user, ['title' => 'مقال منشور للاختبار', 'body' => 'محتوى المقال الكامل هنا.']);
        $service->submitForReview($user, $article);
        $service->approveArticle($staff, $article);

        return $article->fresh();
    }

    public function test_guest_can_browse_the_public_articles_index(): void
    {
        $this->publishedArticle();

        $this->get('/articles')->assertOk()->assertSee('مقال منشور للاختبار');
    }

    public function test_guest_can_view_a_published_article(): void
    {
        $article = $this->publishedArticle();

        $this->get("/articles/{$article->slug}")->assertOk()->assertSee('محتوى المقال الكامل هنا.');
    }

    public function test_guest_gets_404_for_a_draft_article(): void
    {
        $user = User::factory()->create();
        $staff = User::factory()->create(['is_platform_staff' => true]);
        $service = app(ArticleService::class);
        $author = $service->requestAuthorship($user, 'نبذة', 'محامٍ');
        $service->approveAuthor($staff, $author);
        $draft = $service->createDraft($user, ['title' => 'مسودة غير منشورة', 'body' => 'محتوى']);

        $this->get("/articles/{$draft->slug}")->assertNotFound();
    }

    public function test_dashboard_requires_activating_the_free_app_first(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/apps/articles')->assertForbidden();
    }

    public function test_full_authorship_and_publishing_flow_via_real_http_requests(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $user = User::factory()->create();
        $item = \App\Models\MarketplaceItem::where('key', 'articles')->firstOrFail();
        app(\App\Services\SubscriptionService::class)->subscribeUserToFreeItem($user, $item);

        $this->actingAs($user)->get('/apps/articles')->assertOk();

        $this->actingAs($user)->post('/apps/articles/become-author', [
            'bio' => 'نبذة كافية عن الخبرة القانونية.',
            'expertise' => 'محامٍ متخصص بقانون الشركات',
        ])->assertRedirect();

        $author = ArticleAuthor::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('pending', $author->status);

        $staff = User::factory()->create(['is_platform_staff' => true]);
        app(ArticleService::class)->approveAuthor($staff, $author);

        $this->actingAs($user)->post('/apps/articles', [
            'title' => 'مقال عبر HTTP',
            'body' => 'محتوى المقال المُرسَل عبر HTTP فعليًا.',
        ])->assertRedirect();

        $article = Article::where('title', 'مقال عبر HTTP')->firstOrFail();

        $this->actingAs($user)->post("/apps/articles/{$article->id}/submit")->assertRedirect();
        $this->assertSame('pending_review', $article->fresh()->status);

        app(ArticleService::class)->approveArticle($staff, $article);

        $this->get("/articles/{$article->fresh()->slug}")->assertOk()->assertSee('مقال عبر HTTP');
    }

    public function test_stranger_cannot_edit_another_authors_article_via_http(): void
    {
        $article = $this->publishedArticle();

        $this->seed(MarketplaceCatalogSeeder::class);
        $stranger = User::factory()->create();
        $item = \App\Models\MarketplaceItem::where('key', 'articles')->firstOrFail();
        app(\App\Services\SubscriptionService::class)->subscribeUserToFreeItem($stranger, $item);

        $this->actingAs($stranger)->get("/apps/articles/{$article->id}/edit")->assertForbidden();
    }
}
