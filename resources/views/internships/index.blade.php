<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <span class="h-10 w-10 rounded-xl bg-brand-50 text-brand-700 flex items-center justify-center ring-1 ring-inset ring-brand-100">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.62 48.62 0 0112 20.904a48.62 48.62 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.636 50.636 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5" />
                </svg>
            </span>
            <div>
                <h2 class="font-bold text-xl text-gray-900 leading-tight">بوابة التدريب التعاوني</h2>
                <p class="text-sm text-gray-500">{{ $opportunities->total() }} فرصة تدريب متاحة لدى مكاتب وشركات</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex items-center gap-2 flex-wrap mb-8">
            <a href="{{ route('internships.index') }}"
                class="px-4 py-1.5 rounded-full text-sm font-medium transition-colors {{ ! request('category') ? 'bg-brand-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' }}">
                الكل
            </a>
            @foreach (['قانوني', 'مالي', 'محاسبي', 'عام'] as $cat)
                <a href="{{ route('internships.index', ['category' => $cat]) }}"
                    class="px-4 py-1.5 rounded-full text-sm font-medium transition-colors {{ request('category') === $cat ? 'bg-brand-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' }}">
                    {{ $cat }}
                </a>
            @endforeach

            <a href="{{ route('internships.dashboard.index') }}" class="ms-auto text-sm text-brand-700 hover:underline font-medium">
                لديك فرصة تدريب؟ انشرها ←
            </a>
        </div>

        @if ($opportunities->isEmpty())
            <div class="bg-white border border-gray-100 rounded-2xl p-12 text-center text-gray-500">
                لا توجد فرص {{ request('category') ? 'بهذا التصنيف' : '' }} حاليًا.
            </div>
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 mb-8">
                @foreach ($opportunities as $opportunity)
                    <a href="{{ route('internships.show', $opportunity) }}" class="group block bg-white border border-gray-100 rounded-xl shadow-sm p-5 hover:shadow-lg hover:border-brand-200 hover:-translate-y-0.5 transition-all duration-200">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="text-xs bg-gray-50 text-gray-500 px-2 py-0.5 rounded-full ring-1 ring-inset ring-gray-200">{{ $opportunity->category }}</span>
                            @if ($opportunity->location)
                                <span class="text-xs text-gray-400">{{ $opportunity->location }}</span>
                            @endif
                        </div>
                        <h3 class="font-bold text-gray-900 group-hover:text-brand-700 transition-colors mb-2">{{ $opportunity->title }}</h3>
                        <p class="text-sm text-gray-500 leading-relaxed mb-3 line-clamp-2">{{ $opportunity->description }}</p>
                        <p class="text-xs text-gray-400">{{ $opportunity->user->name }}{{ $opportunity->duration ? ' · '.$opportunity->duration : '' }}</p>
                    </a>
                @endforeach
            </div>

            {{ $opportunities->links() }}
        @endif
    </div>
</x-app-layout>
