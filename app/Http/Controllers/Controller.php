<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * AuthorizesRequests أُضيفت بـPhase 2B لتفعيل $this->authorize() — أول
 * استهلاك حقيقي للـPolicies بالمشروع (OrganizationPolicy). إضافة معيارية
 * قياسية بـLaravel، لا تغيّر سلوك أي Controller موجود لم يستخدمها بعد.
 */
abstract class Controller
{
    use AuthorizesRequests;
}
