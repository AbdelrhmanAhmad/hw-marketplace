<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PasswordResetLinkController extends Controller
{
    /**
     * Customer password recovery lives on Core (identity authority).
     */
    public function create(): RedirectResponse
    {
        $core = rtrim((string) config('services.core.url', ''), '/');

        if ($core === '') {
            return redirect()->route('auth.core.redirect', ['intended' => '/marketplace']);
        }

        // Core member password-forgot OTP flow.
        return redirect()->away($core.'/user/password/forgot');
    }

    /**
     * Local forgot-password disabled for customers.
     */
    public function store(Request $request): RedirectResponse
    {
        return $this->create();
    }
}
