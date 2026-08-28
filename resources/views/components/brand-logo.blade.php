@props(['subtitle' => null, 'iconSize' => 'h-9 w-9', 'dark' => false])

<div {{ $attributes->merge(['class' => 'flex items-center gap-3']) }}>
    <img src="{{ asset('images/brand/logo-mark.svg') }}" alt="حكم ورقم" class="{{ $iconSize }} shrink-0">
    <div class="leading-tight">
        <span @class([
            'font-bold text-xl block',
            'text-white' => $dark,
            'text-gray-900' => ! $dark,
        ])>حكم ورقم</span>
        @if ($subtitle)
            <span @class([
                'text-xs block',
                'text-brand-100' => $dark,
                'text-brand-700' => ! $dark,
            ])>{{ $subtitle }}</span>
        @endif
    </div>
</div>
