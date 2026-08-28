<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Support\ActiveOrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 2A — راجع docs/phase-2a-implementation-specification.md قسم C.
 * لا يمنح أي صلاحية إدارية (AD-011) — تبديل هوية العمل فقط.
 */
class OrganizationContextController extends Controller
{
    public function switch(Organization $organization): RedirectResponse
    {
        try {
            ActiveOrganizationContext::switchTo(Auth::user(), $organization);
        } catch (InvalidArgumentException) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return back();
    }

    public function switchToPersonal(): RedirectResponse
    {
        ActiveOrganizationContext::switchToPersonal();

        return back();
    }
}
