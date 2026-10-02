<?php

namespace App\Policies;

use App\Models\TrainingOpportunity;
use App\Models\User;

/**
 * بوابة التدريب التعاوني — ملكية الفرصة فقط. النشر لا يتطلب موافقة إدارية
 * (نفس قرار مجتمع الخدمات) — الأهلية للنشر محكومة بـmarketplace.entitled:internships
 * على مستوى لوحة "فرصي" فقط.
 */
class TrainingOpportunityPolicy
{
    /** عرض الفرصة بلوحة "فرصي" (بأي حالة) — الصفحة العامة تعرض المفتوح فقط. */
    public function view(User $user, TrainingOpportunity $opportunity): bool
    {
        return $opportunity->user_id === $user->id;
    }

    public function close(User $user, TrainingOpportunity $opportunity): bool
    {
        return $opportunity->user_id === $user->id;
    }
}
