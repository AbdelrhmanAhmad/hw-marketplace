{{-- الموظفون — عمود مكافأة نهاية الخدمة محسوب حيًا دائمًا، لا يُخزَّن أبدًا --}}
<div x-show="tab === 'employees'" x-cloak class="space-y-6">
    <form action="{{ route('bankruptcy-tech.cases.employees.store', $case) }}" method="POST" class="bg-white border border-gray-100 rounded-2xl p-6 grid sm:grid-cols-2 gap-4">
        @csrf
        <input type="text" name="name" placeholder="اسم الموظف *" required class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500" value="{{ old('name') }}">
        <input type="text" name="nationality" placeholder="الجنسية" class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
        <input type="text" name="iqama" placeholder="رقم الهوية/الإقامة" class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
        <input type="number" step="0.01" min="0.01" name="salary" placeholder="الراتب الشهري (ر.س) *" required class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500" value="{{ old('salary') }}">
        <div class="sm:col-span-2">
            <input type="date" name="join_date" required class="w-full rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
            <p class="text-xs text-gray-400 mt-1">تاريخ الالتحاق * — يُستخدَم لحساب مكافأة نهاية الخدمة.</p>
        </div>
        <button type="submit" class="sm:col-span-2 bg-brand-600 hover:bg-brand-700 text-white rounded-full py-2.5 text-sm font-semibold transition-colors">إضافة موظف</button>
    </form>

    @if ($case->employees->isNotEmpty())
        <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs">
                    <tr>
                        <th class="text-right px-5 py-3">الموظف</th>
                        <th class="text-right px-5 py-3">الجنسية</th>
                        <th class="text-right px-5 py-3">الراتب</th>
                        <th class="text-right px-5 py-3">تاريخ الالتحاق</th>
                        <th class="text-right px-5 py-3">مكافأة نهاية الخدمة</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($case->employees as $employee)
                        <tr>
                            <td class="px-5 py-3 font-medium text-gray-900">{{ $employee->name }}</td>
                            <td class="px-5 py-3 text-gray-500">{{ $employee->nationality }}</td>
                            <td class="px-5 py-3 text-gray-800 font-mono">{{ number_format($employee->salary, 2) }}</td>
                            <td class="px-5 py-3 text-gray-500">{{ $employee->join_date->translatedFormat('d F Y') }}</td>
                            <td class="px-5 py-3 text-brand-700 font-mono font-semibold">{{ number_format($employee->eosb(), 2) }} ر.س</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="text-center text-gray-400 py-10">لا موظفون مُضافون بعد.</div>
    @endif
</div>
