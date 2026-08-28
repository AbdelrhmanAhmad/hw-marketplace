<nav x-data="{ open: false }" class="bg-white/90 backdrop-blur sticky top-0 z-40 border-b border-gray-100 shadow-sm">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-20">
            <div class="flex items-center">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('platform.home') }}" class="hover:opacity-80 transition-opacity">
                        <x-brand-logo subtitle="بوابة معرفة" icon-size="h-12 w-12" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 space-x-reverse sm:-my-px sm:ms-14 sm:me-10 sm:flex">
                    <x-nav-link :href="route('marefa.home')" :active="request()->routeIs('marefa.home')">
                        الرئيسية
                    </x-nav-link>
                    <x-nav-link :href="route('laws.index')" :active="request()->routeIs('laws.*')">
                        الأنظمة
                    </x-nav-link>
                    <x-nav-link :href="route('updates.index')" :active="request()->routeIs('updates.*')">
                        آخر التحديثات
                    </x-nav-link>
                    <x-nav-link :href="route('calculators.gratuity')" :active="request()->routeIs('calculators.*')">
                        الحاسبات
                    </x-nav-link>
                    @auth
                        <x-nav-link :href="route('bookmarks.index')" :active="request()->routeIs('bookmarks.*')">
                            المفضلة
                        </x-nav-link>
                    @endauth
                </div>
            </div>

            @auth
                <!-- Settings Dropdown -->
                <div class="hidden sm:flex sm:items-center sm:ms-6">
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center gap-2 px-2 py-1.5 rounded-full text-sm font-medium text-gray-600 hover:bg-gray-50 focus:outline-none transition ease-in-out duration-150">
                                <span class="h-8 w-8 rounded-full bg-brand-600 text-white flex items-center justify-center text-xs font-bold">
                                    {{ Str::of(Auth::user()->name)->substr(0, 1) }}
                                </span>
                                <span>{{ Auth::user()->name }}</span>

                                <svg class="fill-current h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link :href="route('profile.edit')">
                                الملف الشخصي
                            </x-dropdown-link>

                            <!-- Authentication -->
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf

                                <x-dropdown-link :href="route('logout')"
                                        onclick="event.preventDefault();
                                                    this.closest('form').submit();">
                                    تسجيل الخروج
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </div>
            @else
                <div class="hidden sm:flex sm:items-center sm:ms-6 gap-4">
                    <a href="{{ route('login') }}" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">تسجيل الدخول</a>
                    <a href="{{ route('register') }}" class="text-sm px-4 py-2 rounded-full bg-brand-600 text-white shadow-sm hover:bg-brand-700 hover:shadow transition-all">إنشاء حساب</a>
                </div>
            @endauth

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('marefa.home')" :active="request()->routeIs('marefa.home')">
                الرئيسية
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('laws.index')" :active="request()->routeIs('laws.*')">
                الأنظمة
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('updates.index')" :active="request()->routeIs('updates.*')">
                آخر التحديثات
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('calculators.gratuity')" :active="request()->routeIs('calculators.*')">
                الحاسبات
            </x-responsive-nav-link>
            @auth
                <x-responsive-nav-link :href="route('bookmarks.index')" :active="request()->routeIs('bookmarks.*')">
                    المفضلة
                </x-responsive-nav-link>
            @endauth
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            @auth
                <div class="px-4">
                    <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                    <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
                </div>

                <div class="mt-3 space-y-1">
                    <x-responsive-nav-link :href="route('profile.edit')">
                        الملف الشخصي
                    </x-responsive-nav-link>

                    <!-- Authentication -->
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf

                        <x-responsive-nav-link :href="route('logout')"
                                onclick="event.preventDefault();
                                            this.closest('form').submit();">
                            تسجيل الخروج
                        </x-responsive-nav-link>
                    </form>
                </div>
            @else
                <div class="mt-3 space-y-1 px-4">
                    <a href="{{ route('login') }}" class="block py-2 text-gray-600">تسجيل الدخول</a>
                    <a href="{{ route('register') }}" class="block py-2 text-brand-700 font-medium">إنشاء حساب</a>
                </div>
            @endauth
        </div>
    </div>
</nav>
