{{-- الجلسات --}}
<div x-show="tab === 'hearings'" x-cloak class="space-y-6">
    <form action="{{ route('bankruptcy-tech.cases.hearings.store', $case) }}" method="POST" class="bg-white border border-gray-100 rounded-2xl p-6 grid sm:grid-cols-2 gap-4">
        @csrf
        <div>
            <input type="date" name="date" required class="w-full rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
            <p class="text-xs text-gray-400 mt-1">تاريخ الجلسة *</p>
        </div>
        <select name="type" required class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
            <option value="">نوع الجلسة *…</option>
            <option value="جلسة_أولى">جلسة أولى</option>
            <option value="جلسة_موضوع">جلسة موضوع</option>
            <option value="جلسة_قرار">جلسة قرار</option>
            <option value="أخرى">أخرى</option>
        </select>
        <textarea name="notes" rows="2" placeholder="ملاحظات (اختياري)" class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500 sm:col-span-2"></textarea>
        <textarea name="result" rows="2" placeholder="النتيجة (إن وُجدت)" class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500 sm:col-span-2"></textarea>
        <button type="submit" class="sm:col-span-2 bg-brand-600 hover:bg-brand-700 text-white rounded-full py-2.5 text-sm font-semibold transition-colors">إضافة جلسة</button>
    </form>

    @forelse ($case->hearings->sortByDesc('date') as $hearing)
        <div class="bg-white border border-gray-100 rounded-2xl p-5">
            <div class="flex items-center justify-between flex-wrap gap-2">
                <p class="font-semibold text-gray-900">{{ $hearing->type }} — {{ $hearing->date->translatedFormat('d F Y') }}</p>
            </div>
            @if ($hearing->notes)
                <p class="text-sm text-gray-600 mt-2">{{ $hearing->notes }}</p>
            @endif
            @if ($hearing->result)
                <p class="text-sm text-brand-700 mt-2 font-medium">النتيجة: {{ $hearing->result }}</p>
            @endif
        </div>
    @empty
        <div class="text-center text-gray-400 py-10">لا جلسات مُسجَّلة بعد.</div>
    @endforelse
</div>
