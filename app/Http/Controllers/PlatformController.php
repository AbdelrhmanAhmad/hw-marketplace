<?php

namespace App\Http\Controllers;

class PlatformController extends Controller
{
    public function index()
    {
        $variant = config('marketplace_ui.home_variant', 'glass');

        return view($variant === 'classic' ? 'platform.home-classic' : 'platform.home');
    }
}
