<x-platform-layout>
    <div class="bg-gradient-to-l from-forest to-brand-700 text-white">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <a href="{{ route('articles.dashboard.index') }}" class="text-xs text-brand-100 hover:text-white transition-colors mb-4 inline-block">← رجوع لمقالاتي</a>
            <h1 class="text-2xl font-bold">مقال جديد</h1>
        </div>
    </div>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        @include('articles.dashboard._form')
    </div>
</x-platform-layout>
