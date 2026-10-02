<x-platform-layout>
    <div class="bg-gradient-to-l from-forest to-brand-700 text-white">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <a href="{{ route('articles.dashboard.index') }}" class="text-xs text-brand-100 hover:text-white transition-colors mb-4 inline-block">← رجوع لمقالاتي</a>
            <h1 class="text-2xl font-bold">تعديل المقال</h1>
        </div>
    </div>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        @if (session('status'))
            <div class="mb-6 bg-brand-50 border border-brand-100 text-brand-700 rounded-2xl px-5 py-4 text-sm font-medium">{{ session('status') }}</div>
        @endif
        @include('articles.dashboard._form')
    </div>
</x-platform-layout>
