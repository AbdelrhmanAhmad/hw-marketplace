<?php

namespace App\Repositories;

use Illuminate\Support\Collection;

/**
 * مصدر واحد لبيانات كتالوج الـMarketplace، بغض النظر عن التخزين الفعلي خلفه.
 * راجع docs/marketplace-architecture-blueprint.md قسم D (Compatibility Layer).
 */
interface MarketplaceCatalogRepository
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function all(): Collection;

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $key): ?array;
}
