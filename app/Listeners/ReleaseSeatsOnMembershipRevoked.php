<?php

namespace App\Listeners;

use App\Events\MembershipRevoked;
use App\Models\User;
use App\Services\SeatService;
use Illuminate\Support\Facades\Auth;

/**
 * BR-2B-04 — أول مستهلك حقيقي لحدث MembershipRevoked (مُعرَّف بلا مستهلك
 * منذ marketplace-implementation-specification.md قسم V). يُبطِل فقط مقاعد
 * ذاك المستخدم بتلك المؤسسة تحديدًا — الاشتراك المؤسسي نفسه لا يتأثر.
 */
class ReleaseSeatsOnMembershipRevoked
{
    public function handle(MembershipRevoked $event): void
    {
        // الفاعل المسجَّل بالتدقيق: من نفَّذ الحذف لو بجلسة مصادَقة (الحالة
        // الشائعة عبر Filament)، وإلا العضو نفسه (حالات نظامية/Tinker).
        $actor = Auth::user() ?? User::find($event->userId);

        if (! $actor) {
            return;
        }

        app(SeatService::class)->releaseAllForUserInOrganization($actor, $event->userId, $event->organizationId);
    }
}
