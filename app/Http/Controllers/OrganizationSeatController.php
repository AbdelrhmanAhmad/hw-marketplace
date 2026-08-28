<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionSeat;
use App\Models\User;
use App\Services\SeatService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Phase 2B — إدارة المقاعد (BR-2B-02: Owner أو Admin). راجع
 * docs/phase-2b-organization-subscription-access-design.md.
 *
 * AD-012 — كل تابع يستدعي $this->authorize() (يستعلم Membership فعليًا
 * بقاعدة البيانات عبر OrganizationPolicy)، ويتحقق صراحة إن كل مورد فرعي
 * (Subscription/Seat) ينتمي فعليًا للمؤسسة المستهدَفة بالمسار — لا اعتماد
 * على Route Model Binding وحده كخط تحقق.
 */
class OrganizationSeatController extends Controller
{
    public function index(Organization $organization)
    {
        $this->authorize('manageSeats', $organization);

        $subscriptions = $organization->marketplaceSubscriptions()
            ->where('status', 'active')
            ->with(['marketplaceItem', 'plan', 'seats.user'])
            ->get();

        $members = Membership::where('organization_id', $organization->id)->with('user')->get();

        return view('platform.organization-seats', [
            'organization' => $organization,
            'subscriptions' => $subscriptions,
            'members' => $members,
        ]);
    }

    public function assign(Organization $organization, Subscription $subscription, User $user, SeatService $seats): RedirectResponse
    {
        $this->authorize('manageSeats', $organization);
        $this->ensureSubscriptionBelongsToOrganization($subscription, $organization);

        // BR-2B-07 — رفض السباق على آخر مقعد يُترجَم لرسالة واضحة، لا صفحة
        // خطأ عامة (500). القرار نفسه (منع التجاوز) يبقى محسومًا داخل
        // SeatService بمعزل تام عن هذي الطبقة (Backend Reject حقيقي).
        try {
            $seats->assign(Auth::user(), $subscription, $user);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['seat' => $exception->getMessage()]);
        }

        return back()->with('seat_assigned', $user->name);
    }

    public function release(Organization $organization, SubscriptionSeat $seat, SeatService $seats): RedirectResponse
    {
        $this->authorize('manageSeats', $organization);
        $this->ensureSubscriptionBelongsToOrganization($seat->subscription, $organization);

        $seats->release(Auth::user(), $seat);

        return back()->with('seat_released', true);
    }

    private function ensureSubscriptionBelongsToOrganization(Subscription $subscription, Organization $organization): void
    {
        abort_unless(
            $subscription->subscriber_type === 'organization' && $subscription->subscriber_id === $organization->id,
            404
        );
    }
}
