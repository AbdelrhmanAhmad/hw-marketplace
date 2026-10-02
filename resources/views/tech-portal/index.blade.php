<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-3">
                <span class="h-10 w-10 rounded-xl bg-maroon-50 text-maroon-600 flex items-center justify-center ring-1 ring-inset ring-maroon-100">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 17.25v-.228a4.5 4.5 0 00-.12-1.03l-2.268-9.64a3.375 3.375 0 00-3.285-2.602H7.923a3.375 3.375 0 00-3.285 2.602l-2.268 9.64a4.5 4.5 0 00-.12 1.03v.228m19.5 0a3 3 0 01-3 3H5.25a3 3 0 01-3-3m19.5 0a3 3 0 00-3-3H5.25a3 3 0 00-3 3m16.5 0h.008v.008h-.008v-.008zm-3 0h.008v.008h-.008v-.008z" />
                    </svg>
                </span>
                <div>
                    <h2 class="font-markazi font-bold text-2xl text-maroon-800 leading-tight">بوابة التقنية</h2>
                    <p class="text-sm text-gray-500">حلول تقنية جاهزة لمكتبك أو نشاطك المهني</p>
                </div>
            </div>
            <a href="{{ route('tech-portal.cart.show') }}" class="relative inline-flex items-center gap-2 bg-maroon-600 hover:bg-maroon-700 text-white rounded-full px-5 py-2.5 text-sm font-semibold transition-colors">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.836l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                </svg>
                السلة
                @if (session('tech_portal_cart') && count(session('tech_portal_cart')))
                    <span class="bg-white text-maroon-700 rounded-full h-5 w-5 flex items-center justify-center text-xs font-bold">{{ count(session('tech_portal_cart')) }}</span>
                @endif
            </a>
        </div>
    </x-slot>

    <div class="bg-cream">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
            @if (session('status'))
                <div class="mb-8 bg-white border border-gold-200 text-gold-800 rounded-2xl px-5 py-4 text-sm font-medium">{{ session('status') }}</div>
            @endif

            @foreach ($services as $category => $categoryServices)
                <div class="mb-10">
                    <h3 class="font-markazi font-semibold text-xl text-maroon-800 mb-4">{{ $category }}</h3>
                    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($categoryServices as $service)
                            @php $inCart = in_array($service->id, session('tech_portal_cart', []), true); @endphp
                            <div class="bg-white border border-maroon-100/70 rounded-xl shadow-sm p-5 flex flex-col">
                                <h4 class="font-markazi font-semibold text-lg text-maroon-800 mb-2">{{ $service->title }}</h4>
                                <p class="text-sm text-gray-500 leading-relaxed mb-4 flex-1">{{ $service->description }}</p>
                                @if ($service->price_note)
                                    <p class="text-xs text-gold-700 font-semibold mb-4">{{ $service->price_note }}</p>
                                @endif
                                @if ($inCart)
                                    <span class="inline-flex items-center justify-center gap-1.5 bg-maroon-50 text-maroon-700 rounded-full px-4 py-2 text-sm font-semibold">
                                        ✓ في السلة
                                    </span>
                                @else
                                    <form method="POST" action="{{ route('tech-portal.cart.add', $service) }}">
                                        @csrf
                                        <button type="submit" class="w-full bg-maroon-600 hover:bg-maroon-700 text-white rounded-full px-4 py-2 text-sm font-semibold transition-colors">
                                            أضف للسلة
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            @if ($services->isEmpty())
                <div class="bg-white border border-maroon-100 rounded-2xl p-12 text-center text-gray-500">
                    لا توجد خدمات متاحة حاليًا.
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
