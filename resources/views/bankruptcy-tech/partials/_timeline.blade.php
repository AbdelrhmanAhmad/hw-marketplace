{{-- الجدول الزمني القانوني — 8 مراحل نظامية ثابتة، مختلف عن تبويب "سجل الأحداث" --}}
<div x-show="tab === 'timeline'" x-cloak class="bg-white border border-gray-100 rounded-2xl p-7">
    @if (! $case->submission_date)
        <p class="text-sm text-gold-700 bg-gold-50 border border-gold-100 rounded-xl px-4 py-3 mb-6">
            لم يُحدَّد تاريخ التقديم بعد (تبويب "نظرة عامة") — الأيام المتبقية أدناه تفتقد نقطة الحساب.
        </p>
    @endif

    <ol class="space-y-4">
        @foreach ($case->timelineEvents as $event)
            @php $daysRemaining = $event->daysRemaining($case->submission_date?->toDateString()); @endphp
            <li class="flex items-start justify-between gap-4 pb-4 border-b border-gray-50 last:border-0 last:pb-0">
                <div class="flex items-start gap-3">
                    <form action="{{ route('bankruptcy-tech.cases.timeline.toggle', [$case, $event]) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="mt-0.5 h-5 w-5 rounded-full border-2 flex items-center justify-center transition-colors {{ $event->done ? 'bg-brand-600 border-brand-600 text-white' : 'border-gray-300 text-transparent hover:border-brand-400' }}">✓</button>
                    </form>
                    <div>
                        <p class="text-sm font-medium {{ $event->done ? 'text-gray-400 line-through' : 'text-gray-900' }}">{{ $event->label }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">اليوم {{ $event->day_offset }} من تاريخ التقديم</p>
                    </div>
                </div>
                @if (! $event->done && $daysRemaining !== null)
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full shrink-0 {{ $daysRemaining < 0 ? 'bg-red-50 text-red-700' : ($daysRemaining <= 3 ? 'bg-gold-50 text-gold-700' : 'bg-gray-100 text-gray-500') }}">
                        {{ $daysRemaining < 0 ? 'متأخرة '.abs($daysRemaining).' يوم' : ($daysRemaining === 0 ? 'اليوم' : 'خلال '.$daysRemaining.' يوم') }}
                    </span>
                @endif
            </li>
        @endforeach
    </ol>
</div>
