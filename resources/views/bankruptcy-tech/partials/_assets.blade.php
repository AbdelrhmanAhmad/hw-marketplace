{{-- الأصول --}}
<div x-show="tab === 'assets'" x-cloak class="space-y-6">
    <form action="{{ route('bankruptcy-tech.cases.assets.store', $case) }}" method="POST" class="bg-white border border-gray-100 rounded-2xl p-6 grid sm:grid-cols-2 gap-4">
        @csrf
        <input type="text" name="name" placeholder="اسم الأصل *" required class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500" value="{{ old('name') }}">
        <input type="number" step="0.01" min="0.01" name="value" placeholder="القيمة (ر.س) *" required class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500" value="{{ old('value') }}">
        <input type="text" name="location" placeholder="الموقع" class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
        <textarea name="description" rows="2" placeholder="وصف (اختياري)" class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500"></textarea>
        <button type="submit" class="sm:col-span-2 bg-brand-600 hover:bg-brand-700 text-white rounded-full py-2.5 text-sm font-semibold transition-colors">إضافة أصل</button>
    </form>

    @if ($case->assets->isNotEmpty())
        <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs">
                    <tr>
                        <th class="text-right px-5 py-3">الأصل</th>
                        <th class="text-right px-5 py-3">القيمة</th>
                        <th class="text-right px-5 py-3">الموقع</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($case->assets as $asset)
                        <tr>
                            <td class="px-5 py-3 font-medium text-gray-900">{{ $asset->name }}</td>
                            <td class="px-5 py-3 text-gray-800 font-mono">{{ number_format($asset->value, 2) }}</td>
                            <td class="px-5 py-3 text-gray-500">{{ $asset->location }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50 font-semibold">
                        <td class="px-5 py-3">الإجمالي</td>
                        <td class="px-5 py-3 font-mono">{{ number_format($case->total_assets, 2) }} ر.س</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @else
        <div class="text-center text-gray-400 py-10">لا أصول مُضافة بعد.</div>
    @endif
</div>
