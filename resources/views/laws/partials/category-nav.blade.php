@php
    $baseParams = request()->only(['q', 'status', 'sort']);
@endphp

<div class="flex flex-wrap gap-2">
    <a
        href="{{ route('laws.index', $baseParams) }}"
        @class([
            'px-3 py-1.5 rounded-full text-xs font-medium border transition-all duration-150',
            'bg-gray-900 text-white border-gray-900' => ! request()->filled('category'),
            'bg-gray-50 text-gray-500 border-transparent hover:bg-white hover:border-gray-300' => request()->filled('category'),
        ])
    >
        كل التصنيفات
    </a>

    @foreach ($categories as $category)
        <a
            href="{{ route('laws.index', $baseParams + ['category' => $category->slug]) }}"
            @class([
                'px-3 py-1.5 rounded-full text-xs font-medium border transition-all duration-150',
                'bg-gold-500 text-white border-gold-500 shadow-sm shadow-gold-500/30' => request('category') === $category->slug,
                'bg-gray-50 text-gray-500 border-transparent hover:bg-white hover:border-gold-200 hover:text-gold-700' => request('category') !== $category->slug,
            ])
        >
            {{ $category->name }}
        </a>
    @endforeach
</div>
