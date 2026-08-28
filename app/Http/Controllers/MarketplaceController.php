<?php

namespace App\Http\Controllers;

use App\Enums\AccessReason;
use App\Models\MarketplaceItem;
use App\Repositories\MarketplaceCatalogRepository;
use App\Services\EntitlementResolver;
use App\Services\SubscriptionService;
use App\Support\ActiveOrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class MarketplaceController extends Controller
{
    public function __construct(
        private readonly MarketplaceCatalogRepository $catalog,
        private readonly EntitlementResolver $entitlement,
    ) {
    }

    public function index(Request $request)
    {
        $allApps = $this->catalog->all()->map(fn (array $app) => $this->withSubscriptionState($app));

        $apps = $allApps
            ->when($request->filled('q'), function ($apps) use ($request) {
                $search = mb_strtolower($request->string('q'));

                return $apps->filter(function (array $app) use ($search) {
                    return str_contains(mb_strtolower($app['name']), $search)
                        || str_contains(mb_strtolower($app['description']), $search);
                });
            })
            ->when($request->get('filter') === 'free', fn ($apps) => $apps->where('free', true))
            ->when($request->get('filter') === 'soon', fn ($apps) => $apps->where('status', 'soon'))
            ->values();

        return view('platform.marketplace', ['apps' => $apps, 'allApps' => $allApps->values()]);
    }

    public function show(string $key)
    {
        $app = $this->catalog->find($key);

        abort_if(! $app, Response::HTTP_NOT_FOUND);

        return view('platform.marketplace-show', ['app' => $this->withSubscriptionState($app)]);
    }

    /**
     * Phase 1b — تفعيل اشتراك شخصي بتطبيق مجاني، ثم فتحه فورًا (نفس تجربة
     * "فعّل وادخل الآن" الحالية بالضبط، لكن مدعومة الآن بـSubscription/Access
     * حقيقيين). Guests لا يصلون لهذا الفعل أبدًا (middleware auth) — تجربتهم
     * ببوابة معرفة العامة تبقى كما هي بلا أي تغيير (القرار القائم لم يتغيّر).
     */
    public function activate(string $key, SubscriptionService $subscriptions): RedirectResponse
    {
        $item = MarketplaceItem::where('key', $key)->firstOrFail();

        abort_unless($item->pricing_model === 'free' && $item->billing_model !== 'organization_only', Response::HTTP_FORBIDDEN);

        $subscriptions->subscribeUserToFreeItem(Auth::user(), $item);

        $app = $this->catalog->find($key);

        return redirect($app['href'] ?? route('platform.marketplace.show', $key));
    }

    public function cancel(string $key, SubscriptionService $subscriptions): RedirectResponse
    {
        $item = MarketplaceItem::where('key', $key)->firstOrFail();

        $subscription = Auth::user()->marketplaceSubscriptions()
            ->where('marketplace_item_id', $item->id)
            ->active()
            ->firstOrFail();

        $subscriptions->cancel($subscription);

        return redirect()->route('my-apps.index')->with('cancelled', $item->name);
    }

    private function withSubscriptionState(array $app): array
    {
        $decision = null;

        if (Auth::check()) {
            $item = MarketplaceItem::where('key', $app['key'])->first();

            if ($item) {
                $decision = $this->entitlement->resolve(Auth::user(), $item, ActiveOrganizationContext::current());
            }
        }

        $app['subscribed'] = $decision?->allowed ?? false;
        $app['access_reason'] = $decision?->reason ?? AccessReason::NeedsSubscription;

        return $app;
    }
}
