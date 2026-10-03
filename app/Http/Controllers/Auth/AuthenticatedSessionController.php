<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Customer login is Core SSO. Filament /admin keeps local staff login.
     */
    public function create(Request $request): RedirectResponse
    {
        $intended = $request->session()->get('url.intended', '/marketplace');

        return redirect()->route('auth.core.redirect', [
            'intended' => is_string($intended) ? $intended : '/marketplace',
        ]);
    }

    /**
     * Local password login disabled for customers — use Core SSO.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        return redirect()->route('auth.core.redirect', [
            'intended' => $request->session()->get('url.intended', '/marketplace'),
        ]);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
