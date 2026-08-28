<?php

namespace App\Providers;

use App\Events\MembershipRevoked;
use App\Listeners\ReleaseSeatsOnMembershipRevoked;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Phase 2B — راجع docs/phase-2b-organization-subscription-access-design.md (BR-2B-04).
        Event::listen(MembershipRevoked::class, ReleaseSeatsOnMembershipRevoked::class);
    }
}
