<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name') }} — دقة رقمية في كل إجراء</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;900&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-cream">
        <header class="bg-white/90 backdrop-blur sticky top-0 z-40 border-b border-gray-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-18 py-3 flex items-center justify-between">
                <a href="{{ route('platform.home') }}">
                    <x-brand-logo icon-size="h-9 w-9" />
                </a>

                @auth
                    <div class="flex items-center gap-4">
                        @php $userOrganizations = auth()->user()->organizations; @endphp
                        @if ($userOrganizations->isNotEmpty())
                            @php $activeOrganization = \App\Support\ActiveOrganizationContext::current(); @endphp
                            <x-dropdown align="left" width="56">
                                <x-slot name="trigger">
                                    <button type="button" class="flex items-center gap-1.5 text-sm text-gray-600 hover:text-gray-900 transition-colors">
                                        {{ $activeOrganization?->name ?? 'شخصي' }}
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                        </svg>
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

                        <a href="{{ route('dashboard') }}" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">
                            لوحتي
                        </a>
                        <a href="{{ route('marefa.home') }}" class="text-sm px-5 py-2.5 rounded-full bg-brand-600 text-white shadow-sm hover:bg-brand-700 hover:shadow transition-all">
                            الدخول للمنصة ←
                        </a>
                    </div>
                @endauth
            </div>
        </header>

        @if (session('interest_success'))
            <div
                x-data="{ show: true }"
                x-show="show"
                x-init="setTimeout(() => show = false, 5000)"
                class="bg-brand-600 text-white text-sm text-center py-2.5 px-4"
            >
                تم تسجيل اهتمامك بـ «{{ session('interest_success') }}» — راح نراسلك أول ما تكون جاهزة. 🎉
            </div>
        @endif

        <main>
            {{ $slot }}
        </main>

        @include('layouts.footer')

        <x-interest-modal />
    </body>
</html>
