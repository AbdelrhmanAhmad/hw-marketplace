@props(['key', 'name', 'tagline', 'description', 'status' => 'soon', 'href' => null, 'icon' => 'legal', 'free' => false, 'subscribed' => false])

@php
    $available = $status === 'available';
@endphp

<a
    href="{{ route('platform.marketplace.show', $key) }}"
    @class([
        'group relative flex flex-col rounded-2xl p-7 transition-all duration-200',
        'bg-white border-2 border-brand-500 shadow-lg shadow-brand-900/5 hover:shadow-xl hover:-translate-y-1' => $available,
        'bg-white border border-gray-100 hover:border-gray-200 hover:shadow-md' => ! $available,
    ])
>
    <div class="absolute top-6 left-6 flex items-center gap-1.5">
        <span @class([
            'text-[11px] font-semibold px-2.5 py-1 rounded-full ring-1 ring-inset',
            'bg-brand-600 text-white ring-brand-600' => $subscribed,
            'bg-brand-50 text-brand-700 ring-brand-100' => $available && ! $subscribed,
            'bg-gold-50 text-gold-700 ring-gold-100' => ! $available,
        ])>
            {{ $subscribed ? 'مفعّل لديك' : ($available ? 'متاحة الآن' : 'قريبًا') }}
        </span>

        @if ($free)
            <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-forest text-white">
                مجاني
            </span>
        @endif
    </div>

    <span @class([
        'h-14 w-14 rounded-2xl flex items-center justify-center mb-6',
        'bg-brand-600 text-white' => $available,
        'bg-gray-100 text-gray-400' => ! $available,
    ])>
        <x-service-icon :name="$icon" class="h-7 w-7" />
    </span>

    <h3 @class(['text-lg font-bold mb-1', 'text-gray-900' => $available, 'text-gray-700' => ! $available])>{{ $name }}</h3>
    <p @class(['text-sm font-medium mb-3', 'text-brand-700' => $available, 'text-gray-400' => ! $available])>{{ $tagline }}</p>
    <p @class(['text-sm leading-relaxed flex-1', 'text-gray-500' => $available, 'text-gray-400' => ! $available])>{{ $description }}</p>

    <span @class([
        'inline-flex items-center gap-1 mt-6 text-sm font-semibold group-hover:gap-2 transition-all',
        'text-brand-700' => $available,
        'text-gray-500' => ! $available,
    ])>
        عرض التفاصيل ←
    </span>
</a>
