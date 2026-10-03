<x-platform-layout>
    <div class="relative overflow-hidden bg-gradient-to-b from-forest to-brand-800 text-white">
        <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(circle at 20% 20%, white 1px, transparent 1px); background-size: 28px 28px;"></div>

        <div class="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 pt-20 pb-20 text-center">
            <h1 class="text-3xl sm:text-5xl font-bold mb-3">حُكم ورقم</h1>
            <p class="text-gold-300 text-lg font-medium mb-5">دقة رقمية في كل إجراء</p>
            <p class="text-brand-50 max-w-2xl mx-auto leading-relaxed mb-8">
                منصة تشغيل سعودية للأعمال القانونية والمالية والمحاسبية — حساب واحد يدير من خلاله
                المهني مكتبه، عملاءه، وكل التطبيقات والخدمات اللي يحتاجها.
            </p>

            <div class="flex items-center justify-center gap-3 flex-wrap">
                <a href="{{ route('platform.marketplace') }}" class="px-7 py-3 rounded-full bg-gold-500 text-gray-900 font-semibold shadow-lg shadow-gold-900/20 hover:bg-gold-400 transition-colors">
                    ادخل متجر التطبيقات
                </a>
                <a href="#sections" class="px-7 py-3 rounded-full border border-white/30 text-white font-medium hover:bg-white/10 transition-colors">
                    تعرّف على المنصة
                </a>
            </div>
        </div>
    </div>

    <div id="sections" class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-20 scroll-mt-20">
        <div class="text-center max-w-xl mx-auto mb-14">
            <h2 class="text-2xl font-bold text-gray-900 mb-3">كيف تُبنى حكم ورقم</h2>
            <p class="text-gray-500">منصة تشغيل كاملة، بخمس طبقات، تُبنى فوق حساب موحّد واحد</p>
        </div>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-5">
            <div class="bg-white border border-gray-100 rounded-2xl p-6 text-center">
                <span class="inline-flex h-11 w-11 rounded-xl bg-brand-50 text-brand-700 items-center justify-center mb-4">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.429 9.75L2.25 12l4.179 2.25m0-4.5l5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L21.75 12l-4.179 2.25m0 0l4.179 2.25L12 21.75 2.25 16.5l4.179-2.25m11.142 0l-5.571 3-5.571-3" />
                    </svg>
                </span>
                <h3 class="font-bold text-gray-900 mb-1 text-sm">Core Platform</h3>
                <p class="text-xs text-gray-500 leading-relaxed">حسابك، مكتبك، صلاحياتك، واشتراكاتك — أساس موحّد لكل شي</p>
            </div>

            <a href="{{ route('platform.marketplace') }}" class="bg-white border-2 border-brand-500 rounded-2xl p-6 text-center hover:shadow-md transition-shadow">
                <span class="inline-flex h-11 w-11 rounded-xl bg-brand-600 text-white items-center justify-center mb-4">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 1.98-4.684 2.582-7.128a.75.75 0 00-.75-.906H5.106M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                    </svg>
                </span>
                <h3 class="font-bold text-gray-900 mb-1 text-sm">Marketplace</h3>
                <p class="text-xs text-gray-500 leading-relaxed">منصة توزيع التطبيقات والخدمات داخل حكم ورقم</p>
            </a>

            <div class="bg-white border border-gray-100 rounded-2xl p-6 text-center">
                <span class="inline-flex h-11 w-11 rounded-xl bg-brand-50 text-brand-700 items-center justify-center mb-4">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                    </svg>
                </span>
                <h3 class="font-bold text-gray-900 mb-1 text-sm">Applications</h3>
                <p class="text-xs text-gray-500 leading-relaxed">تطبيقات متخصصة تبنيها حكم ورقم فوق المنصة الأساسية</p>
            </div>

            <div class="bg-gray-50 border border-gray-100 rounded-2xl p-6 text-center opacity-80">
                <span class="inline-flex h-11 w-11 rounded-xl bg-gray-200 text-gray-400 items-center justify-center mb-4">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                    </svg>
                </span>
                <h3 class="font-bold text-gray-500 mb-1 text-sm">Integrations</h3>
                <p class="text-xs text-gray-400 leading-relaxed mb-2">ربط بمزودين خارجيين (دفع، توقيع إلكتروني، محاسبة سحابية)</p>
                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-gold-50 text-gold-700">قريبًا</span>
            </div>

            <div class="bg-gray-50 border border-gray-100 rounded-2xl p-6 text-center opacity-80">
                <span class="inline-flex h-11 w-11 rounded-xl bg-gray-200 text-gray-400 items-center justify-center mb-4">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </span>
                <h3 class="font-bold text-gray-500 mb-1 text-sm">Partner Ecosystem</h3>
                <p class="text-xs text-gray-400 leading-relaxed mb-2">شركاء خارجيون يبنون تطبيقات وتكاملات فوق المنصة</p>
                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-gold-50 text-gold-700">مستقبلي</span>
            </div>
        </div>
    </div>

    <div class="border-t border-gray-100">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16 grid gap-8 sm:grid-cols-3 text-center">
            <div>
                <span class="h-12 w-12 rounded-full bg-white shadow-sm flex items-center justify-center mx-auto mb-4 text-brand-700">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
                <h3 class="font-bold text-gray-900 mb-1">دقة موثوقة</h3>
                <p class="text-sm text-gray-500">محتوى منظّم ومحدّث وفق مصادر رسمية</p>
            </div>
            <div>
                <span class="h-12 w-12 rounded-full bg-white shadow-sm flex items-center justify-center mx-auto mb-4 text-brand-700">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </span>
                <h3 class="font-bold text-gray-900 mb-1">سرعة في الإجراء</h3>
                <p class="text-sm text-gray-500">أدوات وحاسبات جاهزة توفر عليك الوقت</p>
            </div>
            <div>
                <span class="h-12 w-12 rounded-full bg-white shadow-sm flex items-center justify-center mx-auto mb-4 text-brand-700">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.75c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.75h-.152c-3.196 0-6.1-1.248-8.25-3.286z" />
                    </svg>
                </span>
                <h3 class="font-bold text-gray-900 mb-1">أمان واحترافية</h3>
                <p class="text-sm text-gray-500">تجربة موثوقة تناسب المحامين والشركات والأفراد</p>
            </div>
        </div>
    </div>
</x-platform-layout>
