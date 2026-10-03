<?php

namespace App\Repositories;

use App\Models\MarketplaceItem;
use Illuminate\Support\Collection;

/**
 * يقرأ الكتالوج من marketplace_items، ويحوّل كل عنصر لنفس Shape المصفوفة
 * التي كانت تنتجها App\Support\PlatformApps::all() حرفيًا — صفر تغيير على
 * طبقة العرض (Blade/Controllers) وقت التبديل. راجع Blueprint قسم D.
 */
class DatabaseMarketplaceRepository implements MarketplaceCatalogRepository
{
    public function all(): Collection
    {
        return MarketplaceItem::with(['applicationDetail'])
            ->orderBy('id')
            ->get()
            ->map(fn (MarketplaceItem $item) => $this->toArray($item))
            ->values();
    }

    public function find(string $key): ?array
    {
        $item = MarketplaceItem::with(['applicationDetail'])->where('key', $key)->first();

        return $item ? $this->toArray($item) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(MarketplaceItem $item): array
    {
        $entryRoute = $item->applicationDetail?->entry_route;

        $data = [
            'key' => $item->key,
            'name' => $item->name,
            'tagline' => $item->tagline,
            'description' => $item->description,
            'status' => $entryRoute ? 'available' : 'soon',
            'icon' => $item->icon,
            'audiences' => $item->compatibility ?? [],
            'services' => $item->services ?? [],
            'integrations' => $item->integrations ?? [],
            'free' => $item->pricing_model === 'free',
            // بوابة التقنية أول عنصر حقيقي بهذا النموذج — التفعيل نفسه مجاني
            // (تصفّح/سلة بلا رسوم اشتراك)، لكن كل خدمة بعينها تُدفَع لاحقًا
            // خارج نظام Billing الحالي (لا بوابة دفع بعد) — ليس "مجاني" فعليًا.
            'in_app_purchase' => $item->pricing_model === 'in_app_purchase',
        ];

        if ($entryRoute) {
            $data['href'] = route($entryRoute);
        }

        return $data;
    }
}
