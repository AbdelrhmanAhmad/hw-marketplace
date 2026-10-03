<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name') }} — سوق التطبيقات</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('styles')
    </head>
    <body class="{{ $bodyClass }}">
        <header class="mp-shell-header">
            <div class="mp-shell-header__inner">
                <a href="{{ route('platform.home') }}" class="mp-shell-logo">
                    <img src="{{ asset('images/brand/logo1.png') }}" alt="حكم ورقم" width="120" height="40">
                    <span class="hidden sm:block">
                        <span class="mp-shell-logo__text">حكم ورقم</span>
                        <span class="mp-shell-logo__sub">سوق التطبيقات</span>
                    </span>
                </a>

                <nav class="mp-shell-nav" aria-label="التنقل الرئيسي">
                    <a href="{{ route('platform.home') }}" @class(['is-active' => request()->routeIs('platform.home')])>الرئيسية</a>
                    <a href="{{ route('platform.marketplace') }}" @class(['is-active' => request()->routeIs('platform.marketplace*')])>متجر التطبيقات</a>
                    @auth
                        <a href="{{ route('dashboard') }}" @class(['is-active' => request()->routeIs('dashboard')])>لوحتي</a>
                    @endauth
                </nav>

                <div class="mp-shell-actions">
                    @auth
                        @php $userOrganizations = auth()->user()->organizations; @endphp
                        @if ($userOrganizations->isNotEmpty())
                            @php $activeOrganization = \App\Support\ActiveOrganizationContext::current(); @endphp
                            <x-dropdown align="left" width="56">
                                <x-slot name="trigger">
                                    <button type="button" class="mp-shell-link">
                                        {{ $activeOrganization?->name ?? 'شخصي' }}
                                    </button>
                                </x-slot>
                                <x-slot name="content">
                                    <form action="{{ route('organization-context.personal') }}" method="POST">
                                        @csrf
                                        <button type="submit" @class(['block w-full text-start px-4 py-2 text-sm', 'text-gray-700 hover:bg-gray-50' => $activeOrganization, 'text-brand-700 font-medium' => ! $activeOrganization])>
                                            شخصي
                                        </button>
                                    </form>
                                    @foreach ($userOrganizations as $organization)
                                        <form action="{{ route('organization-context.switch', $organization) }}" method="POST">
                                            @csrf
                                            <button type="submit" @class(['block w-full text-start px-4 py-2 text-sm', 'text-gray-700 hover:bg-gray-50' => $activeOrganization?->id !== $organization->id, 'text-brand-700 font-medium' => $activeOrganization?->id === $organization->id])>
                                                {{ $organization->name }}
                                            </button>
                                        </form>
                                    @endforeach
                                </x-slot>
                            </x-dropdown>
                        @endif

                        <a href="{{ route('platform.marketplace') }}" class="mp-shell-btn mp-shell-btn--primary">الدخول للمتجر</a>
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="mp-shell-btn mp-shell-btn--ghost">الخروج</button>
                        </form>
                    @else
                        <a
                            href="{{ route('login') }}"
                            class="mp-shell-link inline-flex items-center gap-1.5"
                            data-core-sso-link
                            data-core-sso-loading-label="جارٍ تسجيل الدخول عبر حكم ورقم..."
                        >
                            <img src="{{ asset('images/brand/logo-mark.svg') }}" alt="" width="16" height="16" class="opacity-90">
                            <span data-core-sso-label>تسجيل الدخول بحساب حكم ورقم</span>
                        </a>
                        <a
                            href="{{ route('register') }}"
                            class="mp-shell-btn mp-shell-btn--primary inline-flex items-center gap-1.5"
                            data-core-sso-link
                            data-core-sso-loading-label="جارٍ التحويل إلى إنشاء حساب حكم ورقم..."
                            title="سيُستخدم حسابك للدخول إلى جميع خدمات المنصة"
                        >
                            <img src="{{ asset('images/brand/logo-mark.svg') }}" alt="" width="16" height="16" class="brightness-0 invert opacity-95">
                            <span data-core-sso-label>إنشاء حساب حكم ورقم</span>
                        </a>
                    @endauth
                </div>
            </div>
        </header>

        @if (session('sso_login_success'))
            <div
                x-data="{ show: true }"
                x-show="show"
                x-transition
                x-init="setTimeout(() => show = false, 4500)"
                class="bg-brand-600 text-white text-sm text-center py-2.5 px-4"
                role="status"
            >
                تم تسجيل الدخول باستخدام حساب حكم ورقم.
            </div>
        @endif

        @if (session('interest_success'))
            <div
                x-data="{ show: true }"
                x-show="show"
                x-init="setTimeout(() => show = false, 5000)"
                class="bg-brand-600 text-white text-sm text-center py-2 px-4"
            >
                تم تسجيل اهتمامك بـ «{{ session('interest_success') }}» — راح نراسلك أول ما تكون جاهزة.
            </div>
        @endif

        <main>
            {{ $slot }}
        </main>

        @include('layouts.footer')

        <x-interest-modal />

        <script>
            document.querySelectorAll('[data-core-sso-link]').forEach(function (link) {
                link.addEventListener('click', function () {
                    var label = link.querySelector('[data-core-sso-label]');
                    var loading = link.getAttribute('data-core-sso-loading-label');
                    if (label && loading) {
                        label.textContent = loading;
                    }
                    link.setAttribute('aria-busy', 'true');
                    link.style.pointerEvents = 'none';
                    link.style.opacity = '0.85';
                });
            });
        </script>
    </body>
</html>
