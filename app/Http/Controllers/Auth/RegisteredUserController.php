<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RegisteredUserController extends Controller
{
    /**
     * Customer registration is Core SSO (identity authority = Hukm w Rakam).
     */
    public function create(Request $request): RedirectResponse
    {
        return redirect()->route('auth.core.redirect', [
            'intended' => $request->session()->get('url.intended', '/marketplace'),
        ]);
    }

    /**
     * Local registration disabled for customers — use Core SSO.
     */
    public function store(Request $request): RedirectResponse
    {
        return redirect()->route('auth.core.redirect', [
            'intended' => $request->session()->get('url.intended', '/marketplace'),
        ]);
    }
}
