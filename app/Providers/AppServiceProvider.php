<?php

namespace App\Providers;

use App\Contracts\AIServiceInterface;
use App\Events\MembershipRevoked;
use App\Listeners\ReleaseSeatsOnMembershipRevoked;
use App\Services\AI\AnthropicAIService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // طبقة AI المشتركة — راجع docs/marketplace-architecture-blueprint.md
        // §7: كل تطبيق يستهلك AIServiceInterface فقط، لا مزوّد بعينه مباشرة.
        $this->app->bind(AIServiceInterface::class, AnthropicAIService::class);
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
