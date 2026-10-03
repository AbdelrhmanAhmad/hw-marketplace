<?php

namespace App\Http\Controllers\Articles;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreArticleRequest;
use App\Models\Article;
use App\Models\ArticleAuthor;
use App\Models\ArticleCategory;
use App\Services\ArticleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * لوحة المؤلف — "مقالاتي" فقط (لا وصول لمقالات مؤلفين آخرين). يفترض عبور
 * marketplace.entitled:articles أولًا (مسجَّلة بمستوى المجموعة بـroutes/web.php).
 */
class ArticleDashboardController extends Controller
{
    public function index(): View
    {
        $author = ArticleAuthor::where('user_id', Auth::id())->first();

        $articles = $author
            ? $author->articles()->latest()->paginate(10)
            : null;

        return view('articles.dashboard.index', compact('author', 'articles'));
    }

    public function create(): View
    {
        Gate::authorize('create', Article::class);

        return view('articles.dashboard.create', ['categories' => ArticleCategory::orderBy('name')->get()]);
    }

    public function store(StoreArticleRequest $request, ArticleService $service): RedirectResponse
    {
        try {
            $article = $service->createDraft(Auth::user(), $this->dataFromRequest($request));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['title' => $e->getMessage()])->withInput();
        }

        return redirect()->route('articles.dashboard.edit', $article)->with('status', 'أُنشئت المسودة بنجاح.');
    }

    public function edit(Article $article): View
    {
        Gate::authorize('update', $article);

        return view('articles.dashboard.edit', ['article' => $article, 'categories' => ArticleCategory::orderBy('name')->get()]);
    }

    public function update(StoreArticleRequest $request, Article $article, ArticleService $service): RedirectResponse
    {
        try {
            $service->updateDraft(Auth::user(), $article, $this->dataFromRequest($request, $article));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['title' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'تم حفظ التعديلات.');
    }

    public function submit(Article $article, ArticleService $service): RedirectResponse
    {
        try {
            $service->submitForReview(Auth::user(), $article);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', 'أُرسِل المقال للمراجعة.');
    }

    private function dataFromRequest(StoreArticleRequest $request, ?Article $existing = null): array
    {
        $data = $request->safe()->only(['title', 'excerpt', 'body', 'article_category_id']);

        if ($request->hasFile('cover_image')) {
            // نحذف صورة الغلاف القديمة (لو وُجدت) بعد رفع الجديدة — وإلا
            // تبقى ملفات يتيمة على القرص للأبد (نفس درس BankruptcyCaseService::deleteCase).
            $data['cover_image_path'] = $request->file('cover_image')->store('articles/covers', 'public');

            if ($existing?->cover_image_path) {
                Storage::disk('public')->delete($existing->cover_image_path);
            }
        }

        return $data;
    }
}
