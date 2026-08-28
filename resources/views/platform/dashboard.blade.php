<x-platform-layout>
    <div class="bg-gradient-to-l from-brand-700 to-brand-600 text-white">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
            <h1 class="text-2xl font-bold mb-1">مرحبًا، {{ auth()->user()->name }} 👋</h1>
            <p class="text-brand-50 text-sm">لوحتك الموحّدة في حكم ورقم — كل تطبيقاتك من مكان واحد.</p>
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-12">
        <div>
            <h2 class="text-lg font-bold text-gray-900 mb-5">تطبيقاتي</h2>

            @if ($subscribedApps->isEmpty())
                <div class="bg-white border border-gray-100 rounded-2xl p-8 text-center text-gray-500">
                    ما عندك أي تطبيق مفعّل بعد.
                    <a href="{{ route('platform.marketplace') }}" class="text-brand-700 font-medium hover:underline">تصفّح متجر التطبيقات</a>
                </div>
            @else
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($subscribedApps as $app)
                        <a href="{{ $app['href'] ?? '#' }}" class="group flex items-center gap-4 bg-white border border-gray-100 rounded-2xl shadow-sm p-5 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200">
                            <span class="h-12 w-12 shrink-0 rounded-xl bg-brand-600 text-white flex items-center justify-center">
                                <x-service-icon :name="$app['icon']" class="h-6 w-6" />
                            </span>
                            <div>
                                <h3 class="font-bold text-gray-900 group-hover:text-brand-700 transition-colors">{{ $app['name'] }}</h3>
                                <p class="text-xs text-gray-500">{{ $app['tagline'] }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <div>
            <h2 class="text-lg font-bold text-gray-900 mb-5">مكتبي</h2>

            @if ($memberships->isEmpty())
                <div class="bg-white border border-gray-100 rounded-2xl p-8 text-center text-gray-500">
                    لسة ما عندك مكتب أو مؤسسة مرتبطة بحسابك.
                </div>
            @else
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($memberships as $membership)
                        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-5">
                            <h3 class="font-bold text-gray-900">{{ $membership->organization->name }}</h3>
                            <p class="text-xs text-gray-500 mt-1">{{ $membership->role->label() }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div>
            <h2 class="text-lg font-bold text-gray-900 mb-5">روابط سريعة</h2>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('bookmarks.index') }}" class="px-5 py-2.5 rounded-full bg-white border border-gray-200 text-sm font-medium text-gray-700 hover:border-brand-300 hover:text-brand-700 transition-colors">
                    مفضلتي في بوابة معرفة
                </a>
                <a href="{{ route('profile.edit') }}" class="px-5 py-2.5 rounded-full bg-white border border-gray-200 text-sm font-medium text-gray-700 hover:border-brand-300 hover:text-brand-700 transition-colors">
                    الملف الشخصي
                </a>
            </div>
        </div>
    </div>
</x-platform-layout>
