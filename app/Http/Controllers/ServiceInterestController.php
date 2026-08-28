<?php

namespace App\Http\Controllers;

use App\Models\ServiceInterest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceInterestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'service_key' => ['required', 'string', 'max:100'],
            'service_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        ServiceInterest::create($validated);

        return back()->with('interest_success', $validated['service_name']);
    }
}
