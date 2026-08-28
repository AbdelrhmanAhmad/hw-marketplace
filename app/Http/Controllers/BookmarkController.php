<?php

namespace App\Http\Controllers;

use App\Models\LawEntry;
use Illuminate\Http\RedirectResponse;

class BookmarkController extends Controller
{
    public function toggle(LawEntry $lawEntry): RedirectResponse
    {
        auth()->user()->bookmarkedLaws()->toggle($lawEntry->id);

        return back();
    }

    public function index()
    {
        $laws = auth()->user()->bookmarkedLaws()->with('categories')->paginate(12);

        return view('bookmarks.index', compact('laws'));
    }
}
