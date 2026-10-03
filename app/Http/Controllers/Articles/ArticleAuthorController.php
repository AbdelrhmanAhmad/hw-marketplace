<?php

namespace App\Http\Controllers\Articles;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreArticleAuthorRequest;
use App\Services\ArticleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;

class ArticleAuthorController extends Controller
{
    public function create(): View
    {
        return view('articles.dashboard.request-authorship');
    }

    public function store(StoreArticleAuthorRequest $request, ArticleService $service): RedirectResponse
    {
        try {
            $service->requestAuthorship(Auth::user(), $request->string('bio')->toString(), $request->string('expertise')->toString());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['bio' => $e->getMessage()])->withInput();
        }

        return redirect()->route('articles.dashboard.index')->with('status', 'أُرسِل طلب التأليف — بانتظار مراجعة فريق المنصة.');
    }
}
