<x-app-layout>
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <a href="{{ route('articles.index') }}" class="text-sm text-gray-500 hover:text-brand-700 transition-colors mb-6 inline-block">← رجوع لبوابة المقالات</a>

        @if ($article->coverImageUrl())
            <img src="{{ $article->coverImageUrl() }}" alt="" class="w-full h-64 object-cover rounded-2xl mb-6">
        @endif

        <div class="flex items-center gap-2 mb-4">
            @if ($article->category)
                <span class="text-xs bg-brand-50 text-brand-700 px-2.5 py-1 rounded-full">{{ $article->category->name }}</span>
            @endif
            <span class="text-xs text-gray-400">{{ $article->readingMinutes() }} دقائق قراءة</span>
        </div>

        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-4">{{ $article->title }}</h1>

        <div class="flex items-center gap-2 text-sm text-gray-500 mb-8 pb-8 border-b border-gray-100">
            <span class="font-medium text-gray-700">{{ $article->author->user->name }}</span>
            @if ($article->author->expertise)
                <span>· {{ $article->author->expertise }}</span>
            @endif
            <span>· {{ $article->published_at->translatedFormat('d F Y') }}</span>
        </div>

        <div class="text-gray-700 leading-loose text-[15px]" style="white-space: pre-line;">{{ $article->body }}</div>
    </div>
</x-app-layout>
