<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <span class="h-10 w-10 rounded-xl bg-brand-50 text-brand-700 flex items-center justify-center ring-1 ring-inset ring-brand-100">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                </svg>
            </span>
            <div>
                <h2 class="font-bold text-xl text-gray-900 leading-tight">فهرس الأنظمة</h2>
                <p class="text-sm text-gray-500">{{ $laws->total() }} نظام متاح للتصفح والبحث</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-6 mb-8 space-y-5">
            @include('laws.partials.status-nav')

            <div class="border-t border-gray-100 pt-5">
                @include('laws.partials.category-nav')
            </div>

            <form action="{{ route('laws.index') }}" method="GET" class="border-t border-gray-100 pt-5 flex gap-2">
                @if (request()->filled('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
                @if (request()->filled('category'))
                    <input type="hidden" name="category" value="{{ request('category') }}">
                @endif
                @if (request()->filled('sort'))
                    <input type="hidden" name="sort" value="{{ request('sort') }}">
                @endif

                <div class="relative flex-1">
                    <svg class="absolute start-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z" />
                    </svg>
                    <input
                        type="text"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="ابحث بالعنوان أو نص المادة"
                        class="w-full rounded-full border-gray-200 ps-9 focus:ring-brand-500 focus:border-brand-500"
                    >
                </div>
                <button type="submit" class="px-6 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-full text-sm font-medium shadow-sm hover:shadow transition-all">
                    بحث
                </button>
                @if (request()->anyFilled(['q', 'status', 'category', 'sort']))
                    <a href="{{ route('laws.index') }}" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-800 transition-colors">
                        إعادة تعيين
                    </a>
                @endif
            </form>
        </div>

        @if ($laws->isEmpty())
            <div class="bg-white border border-gray-100 rounded-2xl p-12 text-center text-gray-500">
                لا توجد نتائج مطابقة لبحثك.
            </div>
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 mb-8">
                @foreach ($laws as $law)
                    <x-law-card :law="$law" />
                @endforeach
            </div>

            {{ $laws->links() }}
        @endif
    </div>
</x-app-layout>
