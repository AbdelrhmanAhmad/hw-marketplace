@props(['status'])

<span @class([
    'inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full ring-1 ring-inset',
    'bg-brand-50 text-brand-700 ring-brand-600/20' => $status === 'نافذ',
    'bg-gold-50 text-gold-700 ring-gold-600/20' => $status === 'معلق النفاذ',
    'bg-maroon-50 text-maroon-700 ring-maroon-600/10' => $status === 'ملغى',
])>
    <span @class([
        'h-1.5 w-1.5 rounded-full',
        'bg-brand-500' => $status === 'نافذ',
        'bg-gold-500' => $status === 'معلق النفاذ',
        'bg-maroon-600' => $status === 'ملغى',
    ])></span>
    {{ $status }}
</span>
