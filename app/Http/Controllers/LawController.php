<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\LawEntry;
use Illuminate\Http\Request;

class LawController extends Controller
{
    public function index(Request $request)
    {
        $laws = LawEntry::query()
            ->with('categories')
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q');
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('summary', 'like', "%{$search}%")
                        ->orWhereHas('articles', function ($query) use ($search) {
                            $query->where('content', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('category'), function ($query) use ($request) {
                $query->whereHas('categories', fn ($query) => $query->where('slug', $request->string('category')));
            })
            ->when(
                $request->string('sort') === 'newest',
                fn ($query) => $query->latest('gregorian_date'),
                fn ($query) => $query->orderBy('title'),
            )
            ->paginate(12)
            ->withQueryString();

        $categories = Category::orderBy('name')->get();

        return view('laws.index', compact('laws', 'categories'));
    }

    public function show(LawEntry $lawEntry)
    {
        $lawEntry->load(['articles', 'categories', 'updates']);

        $isBookmarked = auth()->check()
            ? auth()->user()->bookmarkedLaws()->where('law_entry_id', $lawEntry->id)->exists()
            : false;

        return view('laws.show', compact('lawEntry', 'isBookmarked'));
    }
}
