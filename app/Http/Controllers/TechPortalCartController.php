<?php

namespace App\Http\Controllers;

use App\Models\TechService;
use App\Services\TechServiceRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * بوابة التقنية — سلة الخدمات. Session فقط (بلا Auth، بلا قاعدة بيانات)
 * حتى لحظة "إرسال الطلب" — عندها فقط تصبح TechServiceRequest حقيقية عبر
 * TechServiceRequestService (BR-013).
 */
class TechPortalCartController extends Controller
{
    private const SESSION_KEY = 'tech_portal_cart';

    public function show(Request $request): View
    {
        $ids = $request->session()->get(self::SESSION_KEY, []);
        $services = TechService::published()->whereIn('id', $ids)->get();

        return view('tech-portal.cart', compact('services'));
    }

    public function add(Request $request, TechService $techService): RedirectResponse
    {
        $ids = $request->session()->get(self::SESSION_KEY, []);

        if (! in_array($techService->id, $ids, true)) {
            $ids[] = $techService->id;
            $request->session()->put(self::SESSION_KEY, $ids);
        }

        return back()->with('status', 'أُضيفت "'.$techService->title.'" للسلة.');
    }

    public function remove(Request $request, TechService $techService): RedirectResponse
    {
        $ids = array_values(array_diff($request->session()->get(self::SESSION_KEY, []), [$techService->id]));
        $request->session()->put(self::SESSION_KEY, $ids);

        return back()->with('status', 'أُزيلت "'.$techService->title.'" من السلة.');
    }

    public function submit(Request $request, TechServiceRequestService $service): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $ids = $request->session()->get(self::SESSION_KEY, []);

        try {
            $service->submitRequest(Auth::user(), $ids, $data);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['cart' => $e->getMessage()])->withInput();
        }

        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('tech-portal.index')->with('status', 'أُرسِل طلبك بنجاح — سيتواصل معك فريقنا التقني قريبًا.');
    }
}
