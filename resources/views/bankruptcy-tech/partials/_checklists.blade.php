@php
$zatca = $case->zatca_checklist ?? [];
$gosi = $case->gosi_checklist ?? [];
$hr = $case->hr_checklist ?? [];
@endphp

{{-- القوائم التنظيمية — ZATCA/GOSI/الموارد البشرية --}}
<div x-show="tab === 'checklists'" x-cloak>
    <form action="{{ route('bankruptcy-tech.cases.checklists.update', $case) }}" method="POST" class="bg-white border border-gray-100 rounded-2xl p-7 space-y-8">
        @csrf
        @method('PATCH')

        <div>
            <h3 class="font-bold text-gray-900 mb-3">هيئة الزكاة والضريبة والجمارك (ZATCA)</h3>
            <input type="text" name="zatca_file_number" placeholder="رقم الملف الضريبي" value="{{ old('zatca_file_number', $case->zatca_file_number) }}" class="w-full rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500 mb-3">
            <div class="grid sm:grid-cols-2 gap-2">
                @foreach ([
                    'accountStatement' => 'كشف حساب ZATCA محدَّث',
                    'vatRegistration' => 'تسجيل ضريبة القيمة المضافة',
                    'zakahCert' => 'شهادة زكاة سارية',
                    'clearanceLetter' => 'خطاب مخالصة ضريبية',
                ] as $key => $label)
                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input type="hidden" name="zatca_checklist[{{ $key }}]" value="0">
                        <input type="checkbox" name="zatca_checklist[{{ $key }}]" value="1" @checked($zatca[$key] ?? false) class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </div>

        <div>
            <h3 class="font-bold text-gray-900 mb-3">التأمينات الاجتماعية (GOSI)</h3>
            <input type="text" name="gosi_file_number" placeholder="رقم ملف التأمينات" value="{{ old('gosi_file_number', $case->gosi_file_number) }}" class="w-full rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500 mb-3">
            <div class="grid sm:grid-cols-2 gap-2">
                @foreach ([
                    'registered' => 'المنشأة مسجَّلة بالتأمينات',
                    'debtsStatement' => 'كشف مديونية الاشتراكات',
                    'clearanceLetter' => 'خطاب مخالصة التأمينات',
                ] as $key => $label)
                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input type="hidden" name="gosi_checklist[{{ $key }}]" value="0">
                        <input type="checkbox" name="gosi_checklist[{{ $key }}]" value="1" @checked($gosi[$key] ?? false) class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </div>

        <div>
            <h3 class="font-bold text-gray-900 mb-3">الموارد البشرية</h3>
            <div class="grid sm:grid-cols-2 gap-2">
                @foreach ([
                    'employeesListed' => 'كشف الموظفين مُسجَّل بالوزارة',
                    'mudadCleared' => 'منصة مُدد — لا مخالفات رواتب',
                    'workPermitsCancelled' => 'طُلب إلغاء تصاريح العمل',
                ] as $key => $label)
                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input type="hidden" name="hr_checklist[{{ $key }}]" value="0">
                        <input type="checkbox" name="hr_checklist[{{ $key }}]" value="1" @checked($hr[$key] ?? false) class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </div>

        <div>
            <h3 class="font-bold text-gray-900 mb-3">جهات أخرى</h3>
            <div class="grid sm:grid-cols-2 gap-2">
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input type="hidden" name="commerce_cr_cancellation_requested" value="0">
                    <input type="checkbox" name="commerce_cr_cancellation_requested" value="1" @checked($case->commerce_cr_cancellation_requested) class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                    طُلب إلغاء السجل التجاري (وزارة التجارة)
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input type="hidden" name="sama_notified" value="0">
                    <input type="checkbox" name="sama_notified" value="1" @checked($case->sama_notified) class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                    أُخطِر البنك المركزي (SAMA)
                </label>
            </div>
        </div>

        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white rounded-full px-6 py-2.5 text-sm font-semibold transition-colors">حفظ القوائم التنظيمية</button>
    </form>
</div>
