<?php

namespace App\Http\Controllers;

use App\Models\ServiceListing;
use App\Services\ServiceListingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * مجتمع الخدمات — الصفحة العامة (بلا Auth، مطابق لـArticleController): أي
 * زائر يتصفح إعلانات مفتوحة ويرسل استفسارًا لصاحبها. لوحة "إعلاناتي"
 * (النشر/الإغلاق) منفصلة تمامًا بـapps/community (Community\ServiceListingDashboardController).
 */
class CommunityController extends Controller
{
    public function index(Request $request): View
    {
        $query = ServiceListing::query()->with('user')->open()->latest();

        if ($category = $request->string('category')->toString()) {
            $query->where('category', $category);
        }

        $listings = $query->paginate(12)->withQueryString();

        return view('community.index', compact('listings'));
    }

    public function show(ServiceListing $listing): View
    {
        abort_unless($listing->isOpen(), 404);
        $listing->load('user');

        $isOwner = Auth::check() && $listing->user_id === Auth::id();

        return view('community.show', [
            'listing' => $listing,
            'isOwner' => $isOwner,
            'inquiries' => $isOwner ? $listing->inquiries()->latest()->get() : null,
        ]);
    }

    public function storeInquiry(Request $request, ServiceListing $listing, ServiceListingService $service): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $service->submitInquiry(Auth::user(), $listing, $data);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['inquiry' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'أُرسِل استفسارك لصاحب الإعلان بنجاح.');
    }
}
