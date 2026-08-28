<?php

namespace App\Repositories;

use App\Support\PlatformApps;
use Illuminate\Support\Collection;

/**
 * الغلاف الحالي (Legacy) فوق App\Support\PlatformApps — يبقى الفعّال افتراضيًا
 * لحد إثبات تطابق DatabaseMarketplaceRepository معه (Parity Check).
 */
class StaticPlatformAppsRepository implements MarketplaceCatalogRepository
{
    public function all(): Collection
    {
        return collect(PlatformApps::all());
    }

    public function find(string $key): ?array
    {
        return collect(PlatformApps::all())->firstWhere('key', $key);
    }
}
