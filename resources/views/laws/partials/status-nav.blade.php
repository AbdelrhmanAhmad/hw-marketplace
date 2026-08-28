@php
    $tabs = [
        ['label' => 'الكل', 'params' => []],
        ['label' => 'الأحدث', 'params' => ['sort' => 'newest']],
        ['label' => 'نشطة', 'params' => ['status' => 'نافذ']],
        ['label' => 'معلّقة النفاذ', 'params' => ['status' => 'معلق النفاذ']],
        ['label' => 'ملغاة', 'params' => ['status' => 'ملغى']],
    ];

    $isActive = function (array $params) {
        if (empty($params)) {
            return ! request()->filled('status') && ! request()->filled('sort');
        }

        foreach ($params as $key => $value) {
            if (request($key) !== $value) {
                return false;
            }
        }

        return true;
    };
@endphp

<div class="flex flex-wrap gap-2">
    @foreach ($tabs as $tab)
        <a
            href="{{ route('laws.index', $tab['params'] + (request()->filled('q') ? ['q' => request('q')] : [])) }}"
            @class([
                'px-4 py-2 rounded-full text-sm font-medium border transition-all duration-150',
                'bg-brand-600 text-white border-brand-600 shadow-sm shadow-brand-600/20' => $isActive($tab['params']),
                'bg-gray-50 text-gray-600 border-transparent hover:bg-white hover:border-brand-200 hover:text-brand-700' => ! $isActive($tab['params']),
            ])
        >
            {{ $tab['label'] }}
        </a>
    @endforeach
</div>
