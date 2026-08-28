<?php

namespace App\Http\Controllers;

use App\Support\PlatformApps;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $appsByKey = collect(PlatformApps::all())->keyBy('key');

        $subscribedApps = $user->subscriptions()
            ->active()
            ->get()
            ->map(fn ($subscription) => $appsByKey->get($subscription->app_key))
            ->filter()
            ->values();

        $memberships = $user->memberships()->with('organization')->get();

        return view('platform.dashboard', [
            'subscribedApps' => $subscribedApps,
            'memberships' => $memberships,
        ]);
    }
}
