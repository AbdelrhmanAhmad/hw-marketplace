<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\CoreSsoClient;
use App\Services\CoreSsoProvisioningService;
use App\Support\SafeIntendedPath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class CoreSsoController extends Controller
{
    public function __construct(
        private readonly CoreSsoClient $client,
        private readonly CoreSsoProvisioningService $provisioning,
    ) {}

    /**
     * Start SSO: store state + intended, redirect browser to Core authorize.
     */
    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->client->isConfigured()) {
            abort(Response::HTTP_SERVICE_UNAVAILABLE, 'Core SSO is not configured.');
        }

        $intended = SafeIntendedPath::sanitize($request->query('intended'), '/marketplace');
        $state = Str::random(40);

        $request->session()->put('core_sso.state', $state);
        $request->session()->put('core_sso.intended', $intended);

        return redirect()->away($this->client->authorizeUrl($state));
    }

    /**
     * Callback: validate state, exchange code server-side, JIT provision, login.
     */
    public function callback(Request $request): RedirectResponse
    {
        if (! $this->client->isConfigured()) {
            abort(Response::HTTP_SERVICE_UNAVAILABLE, 'Core SSO is not configured.');
        }

        $state = (string) $request->query('state', '');
        $code = (string) $request->query('code', '');
        $expected = (string) $request->session()->pull('core_sso.state', '');
        $intended = SafeIntendedPath::sanitize(
            $request->session()->pull('core_sso.intended'),
            '/marketplace',
        );

        if ($expected === '' || $state === '' || ! hash_equals($expected, $state)) {
            return redirect()->route('platform.marketplace')
                ->withErrors(['sso' => 'جلسة الدخول عبر حكم ورقم غير صالحة. أعد المحاولة.']);
        }

        if ($code === '') {
            return redirect()->route('platform.marketplace')
                ->withErrors(['sso' => 'لم يُرجع رمز التفويض من حكم ورقم.']);
        }

        try {
            $coreUser = $this->client->exchange($code);
            $localUser = $this->provisioning->resolveLocalUser($coreUser);
        } catch (RuntimeException $e) {
            return redirect()->route('platform.marketplace')
                ->withErrors(['sso' => $e->getMessage()]);
        }

        Auth::login($localUser, remember: false);
        $request->session()->regenerate();

        return redirect()->to($intended)->with('sso_login_success', true);
    }
}

