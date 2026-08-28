<?php

namespace App\Providers;

use App\Models\Organization;
use App\Models\User;
use App\Repositories\DatabaseMarketplaceRepository;
use App\Repositories\MarketplaceCatalogRepository;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

/**
 * نقطة التبديل الوحيدة بين مصدري الكتالوج (Compatibility Layer).
 * راجع docs/marketplace-architecture-blueprint.md قسم D.
 *
 * الحالة الحالية: DatabaseMarketplaceRepository فعّال — بعد نجاح Parity Check
 * 100% (php artisan marketplace:catalog-parity-check، راجع تقرير Phase 1a).
 * التراجع الفوري لـStaticPlatformAppsRepository يتم بتغيير السطر أدناه فقط.
 */
class MarketplaceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MarketplaceCatalogRepository::class, DatabaseMarketplaceRepository::class);
    }

    public function boot(): void
    {
        // AD-002 نقطة ١ — subscriber_type مقيَّد بقيمتين فقط بالتخزين الفعلي،
        // لا اسم Class كامل (السلوك الافتراضي بـLaravel بلا Morph Map).
        Relation::enforceMorphMap([
            'user' => User::class,
            'organization' => Organization::class,
        ]);
    }
}
