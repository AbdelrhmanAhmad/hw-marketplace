<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <span class="h-10 w-10 rounded-xl bg-brand-50 text-brand-700 flex items-center justify-center ring-1 ring-inset ring-brand-100">
                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" stroke="none">
                    <path d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z" />
                </svg>
            </span>
            <div>
                <h2 class="font-bold text-xl text-gray-900 leading-tight">المفضلة</h2>
                <p class="text-sm text-gray-500">الأنظمة المحفوظة لديك للرجوع إليها بسرعة</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if ($laws->isEmpty())
            <div class="bg-white border border-gray-100 rounded-2xl p-12 text-center text-gray-500">
                لم تُضِف أي نظام إلى المفضلة بعد. تصفح
                <a href="{{ route('laws.index') }}" class="text-brand-700 font-medium hover:underline">فهرس الأنظمة</a>
                وأضف ما يهمك.
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
