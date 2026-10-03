<?php

namespace App\Http\Controllers;

use App\Services\UserAppsResolver;
use App\Support\ActiveOrganizationContext;
use Illuminate\Support\Facades\Auth;

/**
 * Final Execution Sprint — كانت تقرأ `$user->subscriptions()` (الجدول القديم
 * `app_subscriptions`، مُجمَّد الكتابة منذ L1، غير مُهاجَر القراءة حتى الآن) —
 * بينما `MyAppsController` يقرأ النظام الجديد بالكامل. تعارض حي مؤكَّد
 * (`marketplace:subscription-parity-check`): مستخدم ألغى اشتراكه فعليًا
 * بالنظام الجديد وبقيت لوحة التحكم تعرضه "مفعَّلًا" اعتمادًا على السجل
 * القديم. الآن تستهلك `UserAppsResolver` — **نفس المصدر حرفيًا** المُستخدَم
 * بـMy Apps — Dashboard وMy Apps يعرضان نفس الحقيقة دائمًا من الآن.
 * صفر اعتماد تشغيلي متبقٍّ على `app_subscriptions` بأي Controller حي.
 */
class DashboardController extends Controller
{
    public function index(UserAppsResolver $resolver)
    {
        $user = Auth::user();

        $subscribedApps = $resolver->resolve($user, ActiveOrganizationContext::current());

        $memberships = $user->memberships()->with('organization')->get();

        return view('platform.dashboard', [
            'subscribedApps' => $subscribedApps,
            'memberships' => $memberships,
        ]);
    }
}
