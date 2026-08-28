<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * BR-2B-04 — يُطلَق عند حذف Membership. أول مستهلك حقيقي له
 * (كان مُعرَّفًا بلا مستهلك منذ Implementation Spec قسم V).
 */
class MembershipRevoked
{
    use Dispatchable;

    public function __construct(
        public readonly int $userId,
        public readonly int $organizationId,
    ) {
    }
}
