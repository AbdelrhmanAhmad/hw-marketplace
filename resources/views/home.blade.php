<x-app-layout>
    <div class="relative overflow-hidden bg-gradient-to-l from-brand-800 to-brand-600 text-white">
        <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(circle at 80% 20%, white 1px, transparent 1px); background-size: 26px 26px;"></div>

        <div class="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-20 text-center">
            <span class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1 rounded-full bg-white/10 text-gold-300 mb-5">
                جزء من منصة حكم ورقم
            </span>
            <h1 class="text-3xl sm:text-4xl font-bold mb-4">بوابة معرفة القانونية</h1>
            <p class="text-brand-50 max-w-2xl mx-auto mb-9 leading-relaxed">
                منصة إلكترونية تجمع الأنظمة السعودية وتحديثاتها التشريعية، مع أدوات عملية مثل الحاسبات القانونية، لخدمة المحامين والشركات والأفراد.
            </p>
            <form action="{{ route('laws.index') }}" method="GET" class="max-w-xl mx-auto flex gap-2">
                <div class="relative flex-1">
                    <svg class="absolute start-4 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z" />
                    </svg>
                    <input
                        type="text"
                        name="q"
                        placeholder="ابحث عن نظام، مثل: نظام العمل"
                        class="w-full rounded-full border-0 ps-10 pe-4 py-3.5 text-gray-900 shadow-lg focus:ring-2 focus:ring-gold-400"
                    >
                </div>
                <button type="submit" class="bg-gold-500 hover:bg-gold-400 text-gray-900 px-7 py-3.5 rounded-full font-semibold shadow-lg transition-colors">
                    بحث
                </button>
            </form>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
        <div class="mb-14">
            <div class="flex items-center gap-2 mb-5">
                <span class="h-8 w-8 rounded-lg bg-brand-50 text-brand-700 flex items-center justify-center">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h9.75m-9.75 4.5h9.75m5.906-1.906l-2.828-2.828m0 0a3.375 3.375 0 10-4.773-4.773 3.375 3.375 0 004.773 4.773z" />
                    </svg>
                </span>
                <h2 class="text-xl font-bold text-gray-900">تصفح الأنظمة والتشريعات</h2>
            </div>

            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-6 space-y-5">
                @include('laws.partials.status-nav')

                <div class="border-t border-gray-100 pt-5">
                    @include('laws.partials.category-nav')
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-2">
                <span class="h-8 w-8 rounded-lg bg-gold-50 text-gold-600 flex items-center justify-center">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
                <h2 class="text-xl font-bold text-gray-900">آخر التحديثات التشريعية</h2>
            </div>
            <a href="{{ route('updates.index') }}" class="text-brand-700 hover:underline text-sm font-medium">عرض الكل ←</a>
        </div>

        @if ($latestUpdates->isEmpty())
            <p class="text-gray-500">لا توجد تحديثات منشورة بعد.</p>
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 mb-14">
                @foreach ($latestUpdates as $update)
                    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-5 hover:shadow-md transition-shadow">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span>
                            <p class="text-xs text-gray-400">{{ $update->published_at->translatedFormat('d F Y') }}</p>
                        </div>
                        <h3 class="font-bold text-gray-900 mb-2">{{ $update->title }}</h3>
                        <p class="text-sm text-gray-600 line-clamp-3 leading-relaxed">{{ $update->body }}</p>
                        @if ($update->lawEntry)
                            <a href="{{ route('laws.show', $update->lawEntry) }}" class="mt-3 inline-block text-sm text-brand-700 hover:underline font-medium">
                                عرض النظام المرتبط ←
                            </a>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-2">
                <span class="h-8 w-8 rounded-lg bg-brand-50 text-brand-700 flex items-center justify-center">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                    </svg>
                </span>
                <h2 class="text-xl font-bold text-gray-900">أنظمة مضافة حديثًا</h2>
            </div>
            <a href="{{ route('laws.index') }}" class="text-brand-700 hover:underline text-sm font-medium">فهرس الأنظمة كاملًا ←</a>
        </div>

        @if ($featuredLaws->isEmpty())
            <p class="text-gray-500">لا توجد أنظمة مضافة بعد.</p>
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($featuredLaws as $law)
                    <x-law-card :law="$law" show-articles />
                @endforeach
            </div>
        @endif

        <div class="mt-16 relative overflow-hidden bg-gradient-to-br from-forest to-brand-800 rounded-2xl shadow-sm p-10 text-center text-white">
            <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(circle at 15% 85%, white 1px, transparent 1px); background-size: 22px 22px;"></div>
            <div class="relative">
                <span class="inline-flex h-12 w-12 rounded-2xl bg-white/10 items-center justify-center mb-4">
                    <svg class="h-6 w-6 text-gold-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </span>
                <h2 class="text-xl font-bold mb-2">حاسبة مكافأة نهاية الخدمة</h2>
                <p class="text-brand-50 mb-6">احسب مستحقاتك حسب نظام العمل السعودي في أقل من دقيقة.</p>
                <a href="{{ route('calculators.gratuity') }}" class="inline-block bg-gold-500 hover:bg-gold-400 text-gray-900 px-7 py-3 rounded-full font-semibold shadow-lg transition-colors">
                    جرّب الحاسبة
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
