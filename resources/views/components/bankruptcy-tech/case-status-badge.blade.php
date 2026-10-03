@props(['status'])

@php
$labels = [
    'draft' => 'مسودة',
    'preparing' => 'قيد الإعداد',
    'submitted' => 'مُقدَّمة للمحكمة',
    'decided' => 'صدر قرار',
    'closed' => 'مغلقة',
];
$colors = [
    'draft' => 'bg-gray-100 text-gray-600',
    'preparing' => 'bg-brand-50 text-brand-700',
    'submitted' => 'bg-gold-50 text-gold-700',
    'decided' => 'bg-blue-50 text-blue-700',
    'closed' => 'bg-gray-800 text-white',
];
@endphp

<span class="inline-block text-[11px] font-semibold px-2.5 py-1 rounded-full {{ $colors[$status] ?? 'bg-gray-100 text-gray-600' }}">
    {{ $labels[$status] ?? $status }}
</span>
