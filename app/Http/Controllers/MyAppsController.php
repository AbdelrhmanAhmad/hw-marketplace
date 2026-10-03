<?php

namespace App\Http\Controllers;

use App\Services\UserAppsResolver;
use App\Support\ActiveOrganizationContext;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 1b — "تطبيقاتي"، مبنية فوق Subscription/AccessAssignment الحقيقيين.
 * Phase 2B — تضيف مصدر ثانٍ (مقعد بالمؤسسة النشطة) فوق نفس المبدأ.
 * Final Execution Sprint — المنطق انتقل بالكامل لـ`UserAppsResolver` (مصدر
 * وحيد، يستهلكه `DashboardController` أيضًا — لا ازدواج، AD-013).
 */
class MyAppsController extends Controller
{
    public function index(UserAppsResolver $resolver)
    {
        $apps = $resolver->resolve(Auth::user(), ActiveOrganizationContext::current());

        return view('platform.my-apps', ['apps' => $apps]);
    }
}
