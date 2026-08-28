<x-platform-layout>
    <div class="bg-gradient-to-b from-forest to-brand-800 text-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pt-14 pb-16">
            <a href="{{ route('platform.marketplace') }}" class="text-xs text-brand-100 hover:text-white transition-colors mb-6 inline-block">← رجوع للمتجر</a>

            <div class="flex items-start gap-5 flex-wrap">
                <span @class([
                    'h-16 w-16 rounded-2xl flex items-center justify-center shrink-0',
                    'bg-white text-brand-700' => $app['status'] === 'available',
                    'bg-white/10 text-white' => $app['status'] !== 'available',
                ])>
                    <x-service-icon :name="$app['icon']" class="h-8 w-8" />
                </span>

                <div class="flex-1 min-w-[240px]">
                    <div class="flex items-center gap-2 mb-2">
                        @if ($app['subscribed'])
                            <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-white text-brand-700">مفعّل لديك</span>
                        @elseif ($app['status'] === 'available')
                            <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-white/15 text-white">متاحة الآن</span>
                        @else
                            <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-gold-500 text-gray-900">قريبًا</span>
                        @endif

                        @if ($app['free'] ?? false)
                            <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-white/15 text-white">مجاني</span>
                        @endif
                    </div>

                    <h1 class="text-2xl sm:text-3xl font-bold mb-1">{{ $app['name'] }}</h1>
                    <p class="text-brand-50">{{ $app['tagline'] }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
        <div class="grid gap-10 lg:grid-cols-3">
            <div class="lg:col-span-2 space-y-8">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 mb-3">نبذة عن التطبيق</h2>
                    <p class="text-gray-600 leading-relaxed">{{ $app['description'] }}</p>
                </div>

                @if (! empty($app['audiences']))
                    <div>
                        <h2 class="text-lg font-bold text-gray-900 mb-3">لمن يخدم هذا التطبيق</h2>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($app['audiences'] as $audience)
                                <span class="text-sm bg-gray-50 text-gray-600 px-3 py-1.5 rounded-full ring-1 ring-inset ring-gray-200">
                                    {{ \App\Support\PlatformApps::audiences()[$audience] ?? $audience }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="lg:sticky lg:top-24 h-fit">
                <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-6">
                    @if ($app['status'] === 'available')
                        @if (auth()->check() && ! $app['subscribed'])
                            {{-- Phase 1b: مستخدم مسجّل بلا وصول بعد — التفعيل يمر عبر SubscriptionService --}}
                            <form action="{{ route('platform.marketplace.activate', $app['key']) }}" method="POST">
                                @csrf
                                <button
                                    type="submit"
                                    class="w-full text-center bg-brand-600 hover:bg-brand-700 text-white rounded-full py-3 font-semibold shadow-sm hover:shadow transition-all"
                                >
                                    فعّل وادخل الآن ←
                                </button>
                            </form>
                            <p class="text-xs text-gray-400 text-center mt-3">
                                يُفعَّل فورًا بحسابك — بدون خطوات إضافية.
                            </p>
                        @else
                            {{-- زائر غير مسجّل (بوابة معرفة تبقى عامة بلا تسجيل دخول إلزامي) أو مستخدم له وصول فعّال أصلًا --}}
                            <a
                                href="{{ $app['href'] }}"
                                class="block text-center bg-brand-600 hover:bg-brand-700 text-white rounded-full py-3 font-semibold shadow-sm hover:shadow transition-all"
                            >
                                {{ $app['subscribed'] ? 'ادخل إلى التطبيق ←' : 'فعّل وادخل الآن ←' }}
                            </a>
                            @unless ($app['subscribed'])
                                <p class="text-xs text-gray-400 text-center mt-3">
                                    يُفعَّل تلقائيًا أول ما تدخل — بدون خطوات إضافية.
                                </p>
                            @endunless
                        @endif
                    @else
                        <button
                            type="button"
                            x-data
                            x-on:click="window.dispatchEvent(new CustomEvent('open-interest-modal', { detail: { key: '{{ $app['key'] }}', name: '{{ $app['name'] }}' } }))"
                            class="w-full inline-flex items-center justify-center gap-1.5 bg-gold-500 hover:bg-gold-400 text-gray-900 rounded-full py-3 font-semibold transition-colors"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                            </svg>
                            أنا مهتم
                        </button>
                        <p class="text-xs text-gray-400 text-center mt-3">
                            هذا التطبيق قيد التطوير حاليًا — راح نراسلك أول ما يكون جاهزًا.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-platform-layout>
