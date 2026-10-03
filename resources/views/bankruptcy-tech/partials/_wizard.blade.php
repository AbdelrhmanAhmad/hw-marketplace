@php
$wizardFields = [
    'is_active' => 'هل المنشأة نشطة حاليًا؟',
    'has_assets' => 'هل توجد أصول للمنشأة؟',
    'assets_cover_expenses' => 'هل تغطي الأصول مصروفات إجراء التصفية؟',
    'financial_statements_available' => 'هل القوائم المالية لآخر سنتين متوفرة؟',
    'financial_transactions_available' => 'هل كشف المعاملات المالية لآخر 24 شهرًا متوفر؟',
    'creditors_notified' => 'هل تم إخطار الدائنين؟',
    'operated_twelve_months' => 'هل زاولت المنشأة نشاطها 12 شهرًا متتاليًا على الأقل؟',
    'previous_settlement' => 'هل توجد تسوية سابقة لم تنتهِ مدتها؟',
];
$missingLabels = [
    'is_establishment' => 'نوع المنشأة',
    'insolvency_status' => 'حالة الإعسار',
    ...$wizardFields,
];
$missingFields = collect($missingLabels)->filter(fn ($label, $field) => in_array($case->$field, [null, ''], true));
$answeredCount = count($missingLabels) - $missingFields->count();
@endphp

{{-- معالج التشخيص — الأسئلة العشرة اللي تغذّي محرك التوصية القانونية الحتمي --}}
<div x-show="tab === 'wizard'" x-cloak class="space-y-6">
    <div class="bg-white border border-gray-100 rounded-2xl p-7">
        <h2 class="font-bold text-gray-900 mb-1">معالج التشخيص</h2>
        <p class="text-xs text-gray-400 mb-6">إجاباتك هنا تحدّد التوصية القانونية أسفل الصفحة — جاوب على كل الأسئلة (<span class="text-red-500">*</span>) للحصول على توصية دقيقة.</p>

        <form action="{{ route('bankruptcy-tech.cases.wizard.update', $case) }}" method="POST" class="grid sm:grid-cols-2 gap-4">
            @csrf
            @method('PATCH')

            <label class="text-sm text-gray-600 sm:col-span-2 -mb-2">نوع المنشأة <span class="text-red-500">*</span></label>
            <select name="is_establishment" class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500 sm:col-span-2">
                <option value="" disabled @selected(! $case->is_establishment)>اختر…</option>
                <option value="company" @selected($case->is_establishment === 'company')>شركة</option>
                <option value="individual" @selected($case->is_establishment === 'individual')>مؤسسة فردية</option>
            </select>

            @foreach ($wizardFields as $field => $question)
                <div>
                    <label class="text-sm text-gray-600 block mb-1">{{ $question }} <span class="text-red-500">*</span></label>
                    <select name="{{ $field }}" class="w-full rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
                        <option value="" disabled @selected(! $case->$field)>اختر…</option>
                        <option value="yes" @selected($case->$field === 'yes')>نعم</option>
                        <option value="no" @selected($case->$field === 'no')>لا</option>
                    </select>
                </div>
            @endforeach

            <div>
                <label class="text-sm text-gray-600 block mb-1">حالة الإعسار <span class="text-red-500">*</span></label>
                <select name="insolvency_status" class="w-full rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
                    <option value="" disabled @selected(! $case->insolvency_status)>اختر…</option>
                    <option value="actual" @selected($case->insolvency_status === 'actual')>إعسار فعلي — المنشأة متوقفة فعليًا عن السداد</option>
                    <option value="upcoming" @selected($case->insolvency_status === 'upcoming')>إعسار متوقَّع — لم يقع التوقف بعد لكنه متوقَّع قريبًا</option>
                </select>
            </div>

            <button type="submit" class="sm:col-span-2 bg-brand-600 hover:bg-brand-700 text-white rounded-full py-2.5 text-sm font-semibold transition-colors">حفظ الإجابات</button>
        </form>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-7">
        <h2 class="font-bold text-gray-900 mb-1">التوصية القانونية</h2>
        <p class="text-xs text-gray-400 mb-5">تصنيف حتمي محسوب من إجابات المعالج + بيانات الديون والأصول الفعلية — ليس تشخيص AI.</p>

        @if ($isReadyForRecommendation)
            <div class="bg-brand-50 border border-brand-100 rounded-xl p-5">
                <p class="font-semibold text-brand-800">{{ $recommendation->title }}</p>
                <p class="text-sm text-brand-700 mt-1">{{ $recommendation->reason }}</p>
                @if ($recommendation->articles)
                    <p class="text-xs text-brand-600 mt-3">المواد ذات الصلة: {{ implode('، ', $recommendation->articles) }}</p>
                @endif
            </div>

            @if ($deficiencies)
                <div class="mt-6 space-y-2">
                    @foreach ($deficiencies as $deficiency)
                        <div class="flex items-start gap-2 text-sm {{ $deficiency->severity === 'critical' ? 'text-red-700' : 'text-gold-700' }}">
                            <span class="mt-1.5 h-1.5 w-1.5 rounded-full shrink-0 {{ $deficiency->severity === 'critical' ? 'bg-red-600' : 'bg-gold-600' }}"></span>
                            <span>{{ $deficiency->message }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="mt-6 text-sm text-green-700">لا توجد نواقص حرجة أو تحذيرية — الملف مكتمل حسب الفحوصات المتاحة.</p>
            @endif
        @else
            {{-- عمدًا بلا إعادة سرد الأسئلة (موجودة أعلى الصفحة مباشرة) — سطر تقدّم واحد فقط. --}}
            <div class="bg-gold-50 border border-gold-100 rounded-xl p-5 flex items-center justify-between gap-4 flex-wrap">
                <p class="text-sm text-gold-800">
                    <span class="font-semibold">{{ $answeredCount }} من {{ count($missingLabels) }}</span>
                    أسئلة مُجابة — أكمل الباقي بالنموذج أعلاه لعرض التوصية.
                </p>
                <div class="h-2 w-32 bg-gold-100 rounded-full overflow-hidden shrink-0">
                    <div class="h-full bg-gold-500 rounded-full" style="width: {{ (int) round($answeredCount / count($missingLabels) * 100) }}%"></div>
                </div>
            </div>
        @endif
    </div>
</div>
