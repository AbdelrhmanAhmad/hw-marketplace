<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceListingRequest;
use App\Models\ServiceListing;
use App\Services\ServiceListingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * لوحة "إعلاناتي" — نشر/إغلاق إعلانات الخدمة (لا مراجعة إدارية، نشر فوري
 * بعكس لوحة المؤلف بالمقالات). يفترض عبور marketplace.entitled:community
 * أولًا (مسجَّلة بمستوى المجموعة بـroutes/web.php). التصفح العام منفصل
 * تمامًا (CommunityController، بلا Auth).
 */
class ServiceListingDashboardController extends Controller
{
    public function index(): View
    {
        $listings = ServiceListing::where('user_id', Auth::id())->latest()->paginate(10);

        return view('community.dashboard.index', compact('listings'));
    }

    public function create(): View
    {
        return view('community.dashboard.create');
    }

    public function store(StoreServiceListingRequest $request, ServiceListingService $service): RedirectResponse
    {
        try {
            $listing = $service->createListing(Auth::user(), $request->validated());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['title' => $e->getMessage()])->withInput();
        }

        return redirect()->route('community.dashboard.show', $listing)->with('status', 'نُشر إعلانك بنجاح.');
    }

    public function show(ServiceListing $listing): View
    {
        Gate::authorize('view', $listing);

        return view('community.dashboard.show', [
            'listing' => $listing,
            'inquiries' => $listing->inquiries()->latest()->get(),
        ]);
    }

    public function close(ServiceListing $listing, ServiceListingService $service): RedirectResponse
    {
        try {
            $service->closeListing(Auth::user(), $listing);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', 'أُوقِف ظهور الإعلان للعامة.');
    }
}
