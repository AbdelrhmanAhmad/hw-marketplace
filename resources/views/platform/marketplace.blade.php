<x-platform-layout>
    <div class="bg-gradient-to-b from-forest to-brand-800 text-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 pb-14 text-center">
            <span class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1 rounded-full bg-white/10 text-gold-300 mb-5">
                منصة حكم ورقم
            </span>
            <h1 class="text-2xl sm:text-4xl font-bold mb-3">متجر التطبيقات والخدمات</h1>
            <p class="text-brand-50 max-w-2xl mx-auto leading-relaxed">
                ابنِ بيئة عملك القانونية والمالية — أضف التطبيقات والخدمات المتخصصة اللي تحتاجها من مكان واحد.
            </p>
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-5 mb-10 flex flex-wrap items-center gap-4">
            <div class="flex flex-wrap gap-2">
                @php
                    $filters = ['' => 'الكل', 'free' => 'مجاني', 'soon' => 'قريبًا'];
                    $currentFilter = request('filter', '');
                @endphp

                @foreach ($filters as $value => $label)
                    <a
                        href="{{ route('platform.marketplace', array_filter(['filter' => $value, 'q' => request('q')])) }}"
                        @class([
                            'px-4 py-2 rounded-full text-sm font-medium border transition-all duration-150',
                            'bg-brand-600 text-white border-brand-600 shadow-sm' => $currentFilter === $value,
                            'bg-gray-50 text-gray-600 border-transparent hover:bg-white hover:border-brand-200 hover:text-brand-700' => $currentFilter !== $value,
                        ])
                    >
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <form action="{{ route('platform.marketplace') }}" method="GET" class="flex-1 min-w-[220px] flex gap-2">
                @if (request()->filled('filter'))
                    <input type="hidden" name="filter" value="{{ request('filter') }}">
                @endif
                <div class="relative flex-1">
                    <svg class="absolute start-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z" />
                    </svg>
                    <input
                        type="text"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="ابحث عن تطبيق أو خدمة"
                        class="w-full rounded-full border-gray-200 ps-9 focus:ring-brand-500 focus:border-brand-500"
                    >
                </div>
                <button type="submit" class="px-5 py-2 bg-gray-900 hover:bg-gray-800 text-white rounded-full text-sm font-medium transition-colors">
                    بحث
                </button>
            </form>
        </div>

        @if ($apps->isEmpty())
            <div class="bg-white border border-gray-100 rounded-2xl p-12 text-center text-gray-500">
                لا توجد نتائج مطابقة لبحثك.
            </div>
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($apps as $app)
                    <x-service-card
                        :key="$app['key']"
                        :name="$app['name']"
                        :tagline="$app['tagline']"
                        :description="$app['description']"
                        :status="$app['status']"
                        :href="$app['href'] ?? null"
                        :icon="$app['icon']"
                        :free="$app['free'] ?? false"
                        :subscribed="$app['subscribed'] ?? false"
                    />
                @endforeach
            </div>
        @endif
    </div>

    <div class="bg-cream border-y border-gray-100 py-20">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-xl mx-auto mb-14">
                <h2 class="text-2xl font-bold text-gray-900 mb-3">التكامل الذكي بين الخدمات</h2>
                <p class="text-gray-500 leading-relaxed">
                    حكم ورقم مو مجرد مجموعة أدوات منفصلة — كل الخدمات والبوابات مربوطة ببعضها ومع المنصة
                    في نظام بيئي واحد متكامل، تتشارك فيه البيانات والسياق لتقديم تجربة موحّدة. مثال:
                    محامي يفتح قضية إفلاس في <span class="font-medium text-gray-700">إفلاس تك</span>، فيقترح عليه
                    <span class="font-medium text-gray-700">محرك مسودة القضية الذكي</span> مسودة أولية تلقائيًا،
                    و<span class="font-medium text-gray-700">بوابة المقالات</span> محتوى ذا صلة بقضيته.
                </p>
            </div>

            @include('platform.partials.integration-diagram', ['apps' => $allApps])
        </div>
    </div>
</x-platform-layout>
