{{-- الدائنون — سجل مالي مستقل عن تبويب "الأطراف"، يغذّي التوصية القانونية --}}
<div x-show="tab === 'creditors'" x-cloak class="space-y-6">
    <form action="{{ route('bankruptcy-tech.cases.creditors.store', $case) }}" method="POST" class="bg-white border border-gray-100 rounded-2xl p-6 grid sm:grid-cols-2 gap-4">
        @csrf
        <input type="text" name="name" placeholder="اسم الدائن *" required class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500" value="{{ old('name') }}">
        <input type="number" step="0.01" min="0.01" name="amount" placeholder="المبلغ (ر.س) *" required class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500" value="{{ old('amount') }}">
        <select name="priority" required class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
            <option value="">أولوية الدين (المادة 52) *…</option>
            <option value="p1_expenses">م1 — مصروفات الإجراء</option>
            <option value="p1_employees">م1 — مستحقات العمال</option>
            <option value="p1_government">م1 — ديون حكومية</option>
            <option value="p2_secured">م2 — دين مضمون برهن</option>
            <option value="p3_unsecured">م3 — دين تجاري عادي</option>
            <option value="p4_deferred">م4 — دين مؤخر (شركاء)</option>
        </select>
        <input type="text" name="type" placeholder="نوع الدين" class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
        <input type="date" name="date" class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
        <input type="text" name="contact" placeholder="بيانات التواصل (اختياري)" class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
        <select name="pledge_type" class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
            <option value="">نوع الرهن (إن وُجد)…</option>
            <option value="عقاري">عقاري</option>
            <option value="تجاري">تجاري</option>
            <option value="مركبة">مركبة</option>
            <option value="معدات">معدات</option>
            <option value="ضمان_شخصي">ضمان شخصي</option>
            <option value="لا_يوجد">لا يوجد</option>
        </select>
        <label class="flex items-center gap-2 text-sm text-gray-600">
            <input type="checkbox" name="pledge_registered" value="1" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
            الرهن مُسجَّل رسميًا
        </label>
        <button type="submit" class="sm:col-span-2 bg-brand-600 hover:bg-brand-700 text-white rounded-full py-2.5 text-sm font-semibold transition-colors">إضافة دائن</button>
    </form>

    @if ($case->creditors->isNotEmpty())
        <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs">
                    <tr>
                        <th class="text-right px-5 py-3">الدائن</th>
                        <th class="text-right px-5 py-3">الأولوية</th>
                        <th class="text-right px-5 py-3">المبلغ</th>
                        <th class="text-right px-5 py-3">النوع</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($case->creditors->sortBy(fn ($c) => $c->priorityRank()) as $creditor)
                        <tr>
                            <td class="px-5 py-3 font-medium text-gray-900">{{ $creditor->name }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $creditor->priorityLabel() }}</td>
                            <td class="px-5 py-3 text-gray-800 font-mono">{{ number_format($creditor->amount, 2) }}</td>
                            <td class="px-5 py-3 text-gray-500">{{ $creditor->type }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50 font-semibold">
                        <td class="px-5 py-3" colspan="2">الإجمالي</td>
                        <td class="px-5 py-3 font-mono">{{ number_format($case->total_debts, 2) }} ر.س</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @else
        <div class="text-center text-gray-400 py-10">لا دائنون مُضافون بعد.</div>
    @endif
</div>
