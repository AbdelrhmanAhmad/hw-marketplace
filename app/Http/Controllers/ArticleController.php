<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleCategory;
use Illuminate\Http\Request;

/**
 * بوابة المقالات — عرض عام بالكامل (لا Auth، لا Entitlement)، مطابق تمامًا
 * لنمط LawController/LegalUpdateController القائم فعليًا. فقط المقالات
 * المنشورة (published) — أي حالة أخرى غير مرئية هنا إطلاقًا.
 */
class ArticleController extends Controller
{
    public function index(Request $request)
    {
        $articles = Article::published()
            ->with(['author.user', 'category'])
            ->when($request->filled('category'), function ($query) use ($request) {
                $query->whereHas('category', fn ($q) => $q->where('slug', $request->string('category')));
            })
            ->latest('published_at')
            ->paginate(12)
            ->withQueryString();

        $categories = ArticleCategory::orderBy('name')->get();

        return view('articles.index', compact('articles', 'categories'));
    }

    public function show(Article $article)
    {
        // لا تسريب لمقالات غير منشورة عبر تخمين الرابط — 404 لغير المالك،
        // نفس منطق abort_unless بإفلاس تك (لكن مقارنة بحالة النشر لا القضية).
        abort_unless($article->isPublished(), 404);

        $article->load(['author.user', 'category']);

        return view('articles.show', compact('article'));
    }
}
