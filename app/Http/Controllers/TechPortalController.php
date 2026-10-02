<?php

namespace App\Http\Controllers;

use App\Models\TechService;
use Illuminate\View\View;

/** بوابة التقنية — الصفحة العامة (بلا Auth): كتالوج خدمات المنصة التقنية. */
class TechPortalController extends Controller
{
    public function index(): View
    {
        $services = TechService::published()->get()->groupBy(fn (TechService $s) => $s->category ?? 'عام');

        return view('tech-portal.index', compact('services'));
    }
}
