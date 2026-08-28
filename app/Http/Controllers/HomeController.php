<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\LawEntry;
use App\Models\LegalUpdate;

class HomeController extends Controller
{
    public function index()
    {
        $latestUpdates = LegalUpdate::with('lawEntry')
            ->latest('published_at')
            ->take(5)
            ->get();

        $featuredLaws = LawEntry::withCount('articles')
            ->latest()
            ->take(6)
            ->get();

        $categories = Category::orderBy('name')->get();

        return view('home', compact('latestUpdates', 'featuredLaws', 'categories'));
    }
}
