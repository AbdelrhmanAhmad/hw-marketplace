<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Subscription;
use App\Models\User;
use App\Repositories\MarketplaceCatalogRepository;
use Illuminate\Support\Collection;

/**
 * Final Execution Sprint — مصدر الحقيقة الوحيد لـ"تطبيقات المستخدم الفعّالة"،
 * يستهلكه Dashboard وMy Apps معًا. قبل هذا التابع، كل واحد منهما كان يبني
 * استعلامه المستقل (Dashboard: app_subscriptions القديم؛ My Apps: النظام
 * الجديد) — تكرار حقيقي لنفس السؤال بمكانين، بالضبط ما يحذّر منه AD-013
 * (Single Source of Truth for Effective Marketplace Access)، اكتُشف كتناقض
 * فعلي حي (`marketplace:subscription-parity-check`) لا نظري: مستخدم واحد
 * على الأقل كان يظهر "مفعَّل" بلوحة التحكم رغم إلغائه الفعلي بالنظام الجديد.
 *
 * لا اعتماد على app_subscriptions (القديم) إطلاقًا — العزل التشغيلي الكامل
 * عنه مكتمل من هذا التابع فصاعدًا. راجع docs/final-execution-baseline.md.
 */
class UserAppsResolver
{
    public function __construct(
        private readonly EntitlementResolver $entitlement,
        private readonly MarketplaceCatalogRepository $catalog,
    ) {
    }

    /**
     * @return Collection<int, array{key: string, name: string, tagline: string, icon: string, href: ?string, source: string}>
     */
    public function resolve(User $user, ?Organization $activeOrganization): Collection
    {
        $personalApps = $user->marketplaceSubscriptions()
            ->active()
            ->with('marketplaceItem')
            ->get()
            ->map(fn (Subscription $subscription) => $this->toAppEntry($subscription->marketplaceItem, 'شخصي', $user, $activeOrganization));

        $organizationApps = collect();

        if ($activeOrganization) {
            $organizationApps = Subscription::where('subscriber_type', 'organization')
                ->where('subscriber_id', $activeOrganization->id)
                ->where('status', 'active')
                ->whereHas('accessAssignments', fn ($query) => $query->where('user_id', $user->id)->where('status', 'active'))
                ->with('marketplaceItem')
                ->get()
                ->map(fn (Subscription $subscription) => $this->toAppEntry($subscription->marketplaceItem, $activeOrganization->name, $user, $activeOrganization));
        }

        return $personalApps->concat($organizationApps)
            ->filter()
            ->unique('key')
            ->values();
    }

    private function toAppEntry($item, string $source, User $user, ?Organization $activeOrganization): ?array
    {
        $decision = $this->entitlement->resolve($user, $item, $activeOrganization);

        if (! $decision->allowed) {
            return null;
        }

        $catalogEntry = $this->catalog->find($item->key);

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
