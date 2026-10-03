<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <span class="h-10 w-10 rounded-xl bg-maroon-50 text-maroon-600 flex items-center justify-center ring-1 ring-inset ring-maroon-100">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                </svg>
            </span>
            <div>
                <h2 class="font-markazi font-bold text-2xl text-maroon-800 leading-tight">مجتمع الخدمات</h2>
                <p class="text-sm text-gray-500">دليل محامين ومختصين — {{ $listings->total() }} إعلان خدمة متاح</p>
            </div>
        </div>
    </x-slot>

    <div class="bg-cream">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
            <div class="flex items-center gap-2 flex-wrap mb-9">
                <a href="{{ route('community.index') }}"
                    class="px-4 py-1.5 rounded-full text-sm font-medium transition-colors {{ ! request('category') ? 'bg-maroon-600 text-white shadow-sm' : 'bg-white border border-maroon-100 text-maroon-700 hover:bg-maroon-50' }}">
                    الكل
                </a>
                @foreach (['قانوني', 'مالي', 'محاسبي', 'عام'] as $cat)
                    <a href="{{ route('community.index', ['category' => $cat]) }}"
                        class="px-4 py-1.5 rounded-full text-sm font-medium transition-colors {{ request('category') === $cat ? 'bg-maroon-600 text-white shadow-sm' : 'bg-white border border-maroon-100 text-maroon-700 hover:bg-maroon-50' }}">
                        {{ $cat }}
                    </a>
                @endforeach

                <a href="{{ route('community.dashboard.index') }}" class="ms-auto text-sm text-gold-700 hover:text-gold-600 font-semibold">
                    اعرض خدمتك ←
                </a>
            </div>

            @if ($listings->isEmpty())
                <div class="bg-white border border-maroon-100 rounded-2xl p-12 text-center text-gray-500">
                    لا توجد إعلانات {{ request('category') ? 'بهذا التصنيف' : '' }} حاليًا.
                </div>
            @else
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 mb-8">
                    @foreach ($listings as $listing)
                        <a href="{{ route('community.show', $listing) }}" class="group block bg-white border border-maroon-100/70 rounded-xl shadow-sm p-5 hover:shadow-lg hover:border-gold-300 hover:-translate-y-0.5 transition-all duration-200">
                            <span class="text-xs bg-maroon-50 text-maroon-700 px-2 py-0.5 rounded-full ring-1 ring-inset ring-maroon-100">{{ $listing->category }}</span>
                            <h3 class="font-markazi font-semibold text-lg text-maroon-800 group-hover:text-gold-700 transition-colors mt-3 mb-2">{{ $listing->title }}</h3>
                            <p class="text-sm text-gray-500 leading-relaxed mb-3 line-clamp-2">{{ $listing->description }}</p>
                            <p class="text-xs text-gray-400">{{ $listing->user->name }} · {{ $listing->created_at->diffForHumans() }}</p>
                        </a>
                    @endforeach
                </div>

                {{ $listings->links() }}
            @endif
        </div>
    </div>
</x-app-layout>
