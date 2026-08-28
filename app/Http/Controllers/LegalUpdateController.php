<?php

namespace App\Http\Controllers;

use App\Models\LegalUpdate;

class LegalUpdateController extends Controller
{
    public function index()
    {
        $updates = LegalUpdate::with('lawEntry')
            ->latest('published_at')
            ->paginate(10);

        return view('updates.index', compact('updates'));
    }
}
