@props(['law', 'showArticles' => false])

<a href="{{ route('laws.show', $law) }}" class="group block bg-white border border-gray-100 rounded-xl shadow-sm p-5 hover:shadow-lg hover:border-brand-200 hover:-translate-y-0.5 transition-all duration-200">
    <div class="flex items-center justify-between mb-3">
        <x-status-badge :status="$law->status" />
        @if ($showArticles && isset($law->articles_count))
            <span class="text-xs text-gray-400">{{ $law->articles_count }} مادة</span>
        @elseif ($law->number)
            <span class="text-xs text-gray-400">رقم {{ $law->number }}</span>
        @endif
    </div>

    <h3 class="font-bold text-gray-900 group-hover:text-brand-700 transition-colors">{{ $law->title }}</h3>

    @if ($law->relationLoaded('categories') && $law->categories->isNotEmpty())
        <div class="flex flex-wrap gap-1.5 mt-3">
            @foreach ($law->categories as $category)
                <span class="text-xs bg-gray-50 text-gray-500 px-2 py-0.5 rounded-full ring-1 ring-inset ring-gray-200">{{ $category->name }}</span>
            @endforeach
        </div>
    @endif
</a>
