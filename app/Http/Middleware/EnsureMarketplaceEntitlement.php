<?php

namespace App\Http\Middleware;

use App\Models\MarketplaceItem;
use App\Services\EntitlementResolver;
use App\Support\ActiveOrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Final Execution Sprint — الحاجز العام أمام أي تطبيق Marketplace حقيقي.
 * "هل يقدر يستخدم هذا التطبيق أصلًا؟" يُحسَم هنا حصرًا عبر EntitlementResolver
 * (AD-013، مصدر القرار الوحيد) — مُعامَل بمفتاح العنصر، قابل لإعادة الاستخدام
 * لأي تطبيق مستقبلي بلا تكرار منطق (`Route::middleware('marketplace.entitled:bankruptcy-tech')`).
 *
 * هذا فحص Entitlement فقط — لا علاقة له بـAuthorization داخل التطبيق نفسه
 * (من يرى قضية بعينها) — ذاك يبقى مسؤولية BankruptcyCasePolicy حصرًا.
 */
class EnsureMarketplaceEntitlement
{
    public function handle(Request $request, Closure $next, string $itemKey): Response
    {
        $item = MarketplaceItem::where('key', $itemKey)->firstOrFail();

        $decision = app(EntitlementResolver::class)->resolve(
            $request->user(),
            $item,
            ActiveOrganizationContext::current(),
        );

        abort_unless($decision->allowed, 403, 'لا تملك وصولًا فعّالًا لهذا التطبيق — فعِّل اشتراكك أولًا من المتجر.');

        return $next($request);
    }
}
