<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <span class="h-10 w-10 rounded-xl bg-brand-50 text-brand-700 flex items-center justify-center ring-1 ring-inset ring-brand-100">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                </svg>
            </span>
            <div>
                <h2 class="font-bold text-xl text-gray-900 leading-tight">بوابة المقالات</h2>
                <p class="text-sm text-gray-500">{{ $articles->total() }} مقالًا وتحليلًا قانونيًا وماليًا من مختصين</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex items-center gap-2 flex-wrap mb-8">
            <a href="{{ route('articles.index') }}"
                class="px-4 py-1.5 rounded-full text-sm font-medium transition-colors {{ ! request('category') ? 'bg-brand-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' }}">
                الكل
            </a>
            @foreach ($categories as $category)
                <a href="{{ route('articles.index', ['category' => $category->slug]) }}"
                    class="px-4 py-1.5 rounded-full text-sm font-medium transition-colors {{ request('category') === $category->slug ? 'bg-brand-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' }}">
                    {{ $category->name }}
                </a>
            @endforeach

            <a href="{{ route('articles.dashboard.index') }}" class="ms-auto text-sm text-brand-700 hover:underline font-medium">
                اكتب مقالًا ←
            </a>
        </div>

        @if ($articles->isEmpty())
            <div class="bg-white border border-gray-100 rounded-2xl p-12 text-center text-gray-500">
                لا توجد مقالات منشورة {{ request('category') ? 'بهذا التصنيف' : '' }} بعد.
            </div>
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 mb-8">
                @foreach ($articles as $article)
                    <a href="{{ route('articles.show', $article) }}" class="group block bg-white border border-gray-100 rounded-xl shadow-sm overflow-hidden hover:shadow-lg hover:border-brand-200 hover:-translate-y-0.5 transition-all duration-200">
                        @if ($article->coverImageUrl())
                            <img src="{{ $article->coverImageUrl() }}" alt="" class="h-36 w-full object-cover">
                        @endif
                        <div class="p-5">
                            <div class="flex items-center justify-between mb-3">
                                @if ($article->category)
                                    <span class="text-xs bg-gray-50 text-gray-500 px-2 py-0.5 rounded-full ring-1 ring-inset ring-gray-200">{{ $article->category->name }}</span>
                                @else
                                    <span></span>
                                @endif
                                <span class="text-xs text-gray-400">{{ $article->readingMinutes() }} د قراءة</span>
                            </div>
                            <h3 class="font-bold text-gray-900 group-hover:text-brand-700 transition-colors mb-2">{{ $article->title }}</h3>
                            @if ($article->excerpt)
                                <p class="text-sm text-gray-500 leading-relaxed mb-3 line-clamp-2">{{ $article->excerpt }}</p>
                            @endif
                            <p class="text-xs text-gray-400">{{ $article->author->user->name }} · {{ $article->published_at->translatedFormat('d F Y') }}</p>
                        </div>
                    </a>
                @endforeach
            </div>

            {{ $articles->links() }}
        @endif
    </div>
</x-app-layout>
