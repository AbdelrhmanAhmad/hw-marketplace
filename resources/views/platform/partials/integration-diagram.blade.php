@php
    // إحداثيات ٨ نقاط موزّعة بالتساوي حول مركز الرسم (نسب مئوية من الحاوية)
    $positions = [
        ['top' => 12.5, 'left' => 50],
        ['top' => 23.48, 'left' => 76.52],
        ['top' => 50, 'left' => 87.5],
        ['top' => 76.52, 'left' => 76.52],
        ['top' => 87.5, 'left' => 50],
        ['top' => 76.52, 'left' => 23.48],
        ['top' => 50, 'left' => 12.5],
        ['top' => 23.48, 'left' => 23.48],
    ];
@endphp

<div class="relative w-full max-w-xl mx-auto aspect-square">
    <svg class="absolute inset-0 w-full h-full" viewBox="0 0 800 800" fill="none">
        {{-- شبكة الروابط بين كل خدمة وكل خدمة أخرى --}}
        @for ($i = 0; $i < count($positions); $i++)
            @continue (! isset($apps[$i]))
            @for ($j = $i + 1; $j < count($positions); $j++)
                @continue (! isset($apps[$j]))
                <line
                    x1="{{ $positions[$i]['left'] * 8 }}" y1="{{ $positions[$i]['top'] * 8 }}"
                    x2="{{ $positions[$j]['left'] * 8 }}" y2="{{ $positions[$j]['top'] * 8 }}"
                    stroke="currentColor"
                    class="text-brand-100"
                    stroke-width="1.5"
                />
            @endfor
        @endfor

        {{-- روابط المركز "حكم ورقم" بكل خدمة --}}
        @foreach ($positions as $i => $pos)
            @if (isset($apps[$i]))
                <line
                    x1="400" y1="400"
                    x2="{{ $pos['left'] * 8 }}" y2="{{ $pos['top'] * 8 }}"
                    stroke="currentColor"
                    class="text-brand-400"
                    stroke-width="2.5"
                    stroke-dasharray="6 6"
                />
            @endif
        @endforeach
    </svg>

    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 flex flex-col items-center">
        <span class="h-20 w-20 rounded-2xl bg-forest shadow-lg shadow-brand-900/20 flex items-center justify-center ring-4 ring-white">
            <img src="{{ asset('images/brand/logo-mark.svg') }}" alt="حكم ورقم" class="h-11 w-11">
        </span>
        <span class="mt-2 text-xs font-bold text-gray-900 bg-white/90 px-2 py-0.5 rounded-full">حكم ورقم</span>
    </div>

    @foreach ($apps as $i => $app)
        @if (isset($positions[$i]))
            <div
                class="absolute -translate-x-1/2 -translate-y-1/2 flex flex-col items-center gap-1.5 w-24 text-center"
                style="top: {{ $positions[$i]['top'] }}%; left: {{ $positions[$i]['left'] }}%;"
            >
                <span @class([
                    'h-11 w-11 rounded-xl flex items-center justify-center shadow-sm ring-1 ring-inset',
                    'bg-brand-600 text-white ring-brand-600' => $app['status'] === 'available',
                    'bg-white text-gray-400 ring-gray-200' => $app['status'] !== 'available',
                ])>
                    <x-service-icon :name="$app['icon']" class="h-5 w-5" />
                </span>
                <span class="text-[11px] font-medium text-gray-600 leading-tight">{{ $app['name'] }}</span>
            </div>
        @endif
    @endforeach
</div>
