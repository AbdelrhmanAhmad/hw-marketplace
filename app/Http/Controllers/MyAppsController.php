<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Repositories\MarketplaceCatalogRepository;
use App\Services\EntitlementResolver;
use App\Support\ActiveOrganizationContext;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 1b — "تطبيقاتي"، مبنية فوق Subscription/AccessAssignment الحقيقيين.
 * Phase 2B — تضيف مصدر ثانٍ (مقعد بالمؤسسة النشطة) فوق نفس المبدأ: وصولك
 * الشخصي ظاهر دائمًا (اتحاد Union)، + وصول المؤسسة النشطة فقط لو وُجد
 * سياق (لا دمج عبر كل مؤسساتك دفعة واحدة — BR-2B-05).
 */
class MyAppsController extends Controller
{
    public function index(EntitlementResolver $entitlement, MarketplaceCatalogRepository $catalog)
    {
        $user = Auth::user();
        $activeOrganization = ActiveOrganizationContext::current();

        $personalApps = $user->marketplaceSubscriptions()
            ->active()
            ->with('marketplaceItem')
            ->get()
            ->map(fn ($subscription) => $this->toAppEntry($subscription->marketplaceItem, 'شخصي', $entitlement, $catalog, $user, $activeOrganization));

        $organizationApps = collect();

        if ($activeOrganization) {
            $organizationApps = Subscription::where('subscriber_type', 'organization')
                ->where('subscriber_id', $activeOrganization->id)
                ->where('status', 'active')
                ->whereHas('accessAssignments', fn ($query) => $query->where('user_id', $user->id)->where('status', 'active'))
                ->with('marketplaceItem')
                ->get()
                ->map(fn ($subscription) => $this->toAppEntry($subscription->marketplaceItem, $activeOrganization->name, $entitlement, $catalog, $user, $activeOrganization));
        }

        $apps = $personalApps->concat($organizationApps)
            ->filter()
            ->unique('key')
            ->values();

        return view('platform.my-apps', ['apps' => $apps]);
    }

    private function toAppEntry($item, string $source, EntitlementResolver $entitlement, MarketplaceCatalogRepository $catalog, $user, $activeOrganization): ?array
    {
        $decision = $entitlement->resolve($user, $item, $activeOrganization);

        if (! $decision->allowed) {
            return null;
        }

        $catalogEntry = $catalog->find($item->key);

        return [
            'key' => $item->key,
            'name' => $item->name,
            'tagline' => $item->tagline,
            'icon' => $item->icon,
            'href' => $catalogEntry['href'] ?? null,
            'source' => $source,
        ];
    }
}
