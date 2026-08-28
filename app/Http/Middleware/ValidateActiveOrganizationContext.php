<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * BR-2A-02 — جلسة تشير لمؤسسة فقد المستخدم عضويته بها تُصحَّح تلقائيًا
 * لـ"شخصي"، بصمت، بلا كسر الطلب. راجع docs/phase-2a-implementation-specification.md.
 */
class ValidateActiveOrganizationContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && session()->has('active_organization_id')) {
            $isStillMember = Auth::user()->organizations()
                ->where('organizations.id', session('active_organization_id'))
                ->exists();

            if (! $isStillMember) {
                session()->forget('active_organization_id');
            }
        }

        return $next($request);
    }
}
