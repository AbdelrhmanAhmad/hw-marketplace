<x-platform-layout>
    <div class="bg-gradient-to-l from-brand-700 to-brand-600 text-white">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
            <h1 class="text-2xl font-bold mb-1">تطبيقاتي</h1>
            <p class="text-brand-50 text-sm">التطبيقات المفعّلة فعليًا بحسابك — يمكنك فتحها مباشرة من هنا.</p>
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        @php $activeOrganization = \App\Support\ActiveOrganizationContext::current(); @endphp
        <p class="text-xs text-gray-400 mb-6">
            أنت الآن تعمل باسم: <span class="font-medium text-gray-600">{{ $activeOrganization?->name ?? 'شخصي' }}</span>
            @if ($activeOrganization && auth()->user()->can('manageSeats', $activeOrganization))
                · <a href="{{ route('organization-seats.index', $activeOrganization) }}" class="text-brand-700 hover:underline">إدارة المقاعد</a>
            @endif
        </p>

        @if (session('activated'))
            <div class="mb-8 bg-brand-50 border border-brand-100 text-brand-700 rounded-2xl px-5 py-4 text-sm font-medium">
                تم! "{{ session('activated') }}" صار جزء من بيئتك.
            </div>
        @endif

        @if (session('cancelled'))
            <div class="mb-8 bg-gray-50 border border-gray-100 text-gray-600 rounded-2xl px-5 py-4 text-sm font-medium">
                تم إلغاء اشتراكك بـ"{{ session('cancelled') }}".
            </div>
        @endif

        @if ($apps->isEmpty())
            <div class="bg-white border border-gray-100 rounded-2xl p-12 text-center text-gray-500">
                ما عندك أي تطبيق مفعّل بعد.
                <a href="{{ route('platform.marketplace') }}" class="text-brand-700 font-medium hover:underline">ابدأ ببوابة معرفة، مجانية بالكامل</a>
            </div>
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($apps as $app)
                    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-5">
                        <div class="flex items-center gap-4 mb-3">
                            <span class="h-12 w-12 shrink-0 rounded-xl bg-brand-600 text-white flex items-center justify-center">
                                <x-service-icon :name="$app['icon']" class="h-6 w-6" />
                            </span>
                            <div>
                                <h3 class="font-bold text-gray-900">{{ $app['name'] }}</h3>
                                <p class="text-xs text-gray-500">{{ $app['tagline'] }}</p>
                            </div>
                        </div>
                        <span @class([
                            'inline-block text-[11px] font-medium px-2.5 py-1 rounded-full mb-3',
                            'bg-gray-50 text-gray-500' => $app['source'] === 'شخصي',
                            'bg-brand-50 text-brand-700' => $app['source'] !== 'شخصي',
                        ])>
                            {{ $app['source'] === 'شخصي' ? 'اشتراكك' : 'اشتراك ' . $app['source'] }}
                        </span>
                        <div class="flex items-center gap-2">
                            <a
                                href="{{ $app['href'] ?? '#' }}"
                                class="flex-1 text-center bg-brand-600 hover:bg-brand-700 text-white rounded-full py-2 text-sm font-semibold transition-colors"
                            >
                                فتح
                            </a>
                            @if ($app['source'] === 'شخصي')
                                <form action="{{ route('platform.marketplace.cancel', $app['key']) }}" method="POST">
                                    @csrf
                                    <button
                                        type="submit"
                                        class="px-4 py-2 rounded-full border border-gray-200 text-gray-500 text-sm hover:border-gray-300 hover:text-gray-700 transition-colors"
                                    >
                                        إلغاء
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-platform-layout>
