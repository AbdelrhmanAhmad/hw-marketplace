<?php

namespace App\Policies;

use App\Models\ServiceListing;
use App\Models\User;

/**
 * مجتمع الخدمات — ملكية الإعلان فقط. النشر نفسه لا يتطلب موافقة (بعكس
 * بوابة المقالات) — أي مستخدم مسجَّل يقدر ينشر مباشرة، الأهلية محكومة
 * بـmarketplace.entitled:community على مستوى لوحة "إعلاناتي" فقط.
 */
class ServiceListingPolicy
{
    /** عرض الإعلان بلوحة "إعلاناتي" (بأي حالة، مفتوح أو مُغلَق) — الصفحة العامة تعرض المفتوح فقط لأي زائر. */
    public function view(User $user, ServiceListing $listing): bool
    {
        return $listing->user_id === $user->id;
    }

    public function close(User $user, ServiceListing $listing): bool
    {
        return $listing->user_id === $user->id;
    }
}
