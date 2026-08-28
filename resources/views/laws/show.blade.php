<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div>
                <a href="{{ route('laws.index') }}" class="text-xs text-gray-400 hover:text-brand-700 transition-colors mb-1 inline-block">← فهرس الأنظمة</a>
                <h2 class="font-bold text-xl text-gray-900 leading-tight">
                    {{ $lawEntry->title }}
                </h2>
            </div>

            @auth
                <form action="{{ route('bookmarks.toggle', $lawEntry) }}" method="POST">
                    @csrf
                    <button
                        type="submit"
                        @class([
                            'inline-flex items-center gap-1.5 text-sm px-4 py-2 rounded-full font-medium border transition-all',
                            'bg-brand-600 text-white border-brand-600 shadow-sm' => $isBookmarked,
                            'bg-white text-brand-700 border-brand-200 hover:bg-brand-50' => ! $isBookmarked,
                        ])
                    >
                        <svg class="h-4 w-4" fill="{{ $isBookmarked ? 'currentColor' : 'none' }}" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z" />
                        </svg>
                        {{ $isBookmarked ? 'محفوظ في المفضلة' : 'أضف للمفضلة' }}
                    </button>
                </form>
            @endauth
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-6 sm:p-8 mb-8">
            <div class="flex flex-wrap items-center gap-2 mb-5">
                <x-status-badge :status="$lawEntry->status" />

                @foreach ($lawEntry->categories as $category)
                    <span class="text-xs bg-gray-50 text-gray-500 px-2.5 py-1 rounded-full ring-1 ring-inset ring-gray-200">{{ $category->name }}</span>
                @endforeach
            </div>

            <dl class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4 text-sm mb-2">
                @if ($lawEntry->number)
                    <div>
                        <dt class="text-gray-400 mb-0.5">رقم النظام</dt>
                        <dd class="text-gray-900 font-medium">{{ $lawEntry->number }}</dd>
                    </div>
                @endif
                @if ($lawEntry->issuing_authority)
                    <div>
                        <dt class="text-gray-400 mb-0.5">الجهة المصدرة</dt>
                        <dd class="text-gray-900 font-medium">{{ $lawEntry->issuing_authority }}</dd>
                    </div>
                @endif
                @if ($lawEntry->hijri_date)
                    <div>
                        <dt class="text-gray-400 mb-0.5">التاريخ الهجري</dt>
                        <dd class="text-gray-900 font-medium">{{ $lawEntry->hijri_date }}</dd>
                    </div>
                @endif
                @if ($lawEntry->gregorian_date)
                    <div>
                        <dt class="text-gray-400 mb-0.5">التاريخ الميلادي</dt>
                        <dd class="text-gray-900 font-medium">{{ $lawEntry->gregorian_date->translatedFormat('d F Y') }}</dd>
                    </div>
                @endif
            </dl>

            @if ($lawEntry->summary)
                <p class="text-gray-600 leading-relaxed border-t border-gray-100 mt-5 pt-5">{{ $lawEntry->summary }}</p>
            @endif

            @if ($lawEntry->source_url)
                <a href="{{ $lawEntry->source_url }}" target="_blank" rel="noopener" class="mt-4 inline-flex items-center gap-1 text-sm text-brand-700 hover:underline font-medium">
                    عرض المصدر الرسمي ←
                </a>
            @endif
        </div>

        <div class="flex items-center gap-2 mb-4">
            <h3 class="text-lg font-bold text-gray-900">مواد النظام</h3>
            @if ($lawEntry->articles->isNotEmpty())
                <span class="text-xs text-gray-400 bg-gray-50 px-2 py-0.5 rounded-full">{{ $lawEntry->articles->count() }}</span>
            @endif
        </div>

        @if ($lawEntry->articles->isEmpty())
            <div class="bg-white border border-gray-100 rounded-2xl p-8 text-center text-gray-500 mb-8">
                لم تُضَف مواد لهذا النظام بعد.
            </div>
        @else
            <div class="space-y-3 mb-10">
                @foreach ($lawEntry->articles as $article)
                    <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-5 hover:border-brand-100 transition-colors">
                        <h4 class="font-semibold text-brand-700 mb-2 text-sm">المادة {{ $article->article_number }}</h4>
                        <p class="text-gray-700 leading-relaxed whitespace-pre-line">{{ $article->content }}</p>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($lawEntry->updates->isNotEmpty())
            <h3 class="text-lg font-bold text-gray-900 mb-4">تحديثات مرتبطة بهذا النظام</h3>
            <div class="space-y-3">
                @foreach ($lawEntry->updates as $update)
                    <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-4">
                        <p class="text-xs text-gray-400 mb-1">{{ $update->published_at->translatedFormat('d F Y') }}</p>
                        <h4 class="font-medium text-gray-900">{{ $update->title }}</h4>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
