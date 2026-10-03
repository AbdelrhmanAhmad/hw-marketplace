@php
$legalDocs = app(\App\Support\BankruptcyLegalDocuments::class);
$documentTexts = [
    'claim' => $legalDocs->claim($case),
    'resolution' => $legalDocs->shareholdersResolution($case),
    'creditors' => $legalDocs->creditorsNotice($case),
    'poa' => $legalDocs->powerOfAttorney($case),
    'financial_letter' => $legalDocs->financialStatementExcuseLetter($case),
    'transactions' => $legalDocs->financialTransactionsStatement($case),
];
$documentLabels = [
    'claim' => 'صحيفة الدعوى',
    'resolution' => 'محضر قرار الشركاء',
    'creditors' => 'إشعار الدائنين',
    'poa' => 'الوكالة الشرعية',
    'financial_letter' => 'خطاب أعذار القوائم المالية',
    'transactions' => 'بيان التصرفات المالية',
];

// إصلاح خلل حقيقي رُصِد بمراجعة مباشرة: المستند كان يُعرَض مليئًا بنقاط
// "................" بلا أي تفسير أو إشارة إن البيانات الناقصة (اسم المدين/
// رقم السجل/اسم المحامي) موجودة بتبويب مختلف تمامًا ("الملف والتشخيص").
$missingCoreProfile = ! $case->debtor_name || ! $case->cr_number || ! $case->attorney_name;
@endphp

{{-- المستندات القانونية — نصوص Server-rendered، تصدير PDF/Word بالكامل Client-side (لا خادم PDF) --}}
<div x-show="tab === 'legal-documents'" x-cloak
     x-data="{ docTab: 'claim', documents: @js($documentTexts), labels: @js($documentLabels) }"
     class="space-y-6">

    @if ($missingCoreProfile)
        <div class="bg-gold-50 border border-gold-100 rounded-2xl p-5 flex items-center justify-between gap-4 flex-wrap">
            <p class="text-sm text-gold-800">
                المستندات أدناه ستحتوي فراغات (<code class="text-xs">................</code>) لحد ما تُكمل <strong>اسم المدين ورقم السجل التجاري واسم المحامي</strong> — هذي البيانات بتبويب "الملف والتشخيص"، لا هذا التبويب.
            </p>
            <button type="button"
                x-on:click="tab = 'overview'; history.replaceState(null, '', '#overview')"
                class="bg-white text-gold-800 border border-gold-200 rounded-full px-4 py-1.5 text-xs font-semibold hover:bg-gold-100 transition-colors shrink-0">
                الانتقال لإكمالها ←
            </button>
        </div>
    @endif

    {{-- بيانات المستند — منقولة هنا من "نظرة عامة" لأنها تُستخدَم هنا حصرًا --}}
    <div class="bg-white border border-gray-100 rounded-2xl p-6">
        <h3 class="font-bold text-gray-900 mb-1 text-sm">بيانات المستند والوكالة الشرعية</h3>
        <p class="text-xs text-gray-400 mb-4">تظهر هذي القيم مباشرة داخل نصوص المستندات أدناه.</p>
        <form action="{{ route('bankruptcy-tech.cases.profile.update', $case) }}" method="POST" class="grid sm:grid-cols-2 gap-4">
            @csrf
            @method('PATCH')
            <input type="hidden" name="_tab" value="legal-documents">
            <div>
                <input type="date" name="document_date" value="{{ old('document_date', $case->document_date?->toDateString()) }}" class="w-full rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
                <p class="text-xs text-gray-400 mt-1">تاريخ المستند</p>
            </div>
            <div>
                <input type="text" name="document_time" placeholder="مثال: 10:30 ص" value="{{ old('document_time', $case->document_time) }}" class="w-full rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
                <p class="text-xs text-gray-400 mt-1">وقت المستند</p>
            </div>
            <div>
                <input type="text" name="poa_number" placeholder="رقم الوكالة" value="{{ old('poa_number', $case->poa_number) }}" class="w-full rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
                <p class="text-xs text-gray-400 mt-1">رقم توثيق الوكالة الشرعية لدى كاتب العدل.</p>
            </div>
            <div>
                <input type="date" name="poa_date" value="{{ old('poa_date', $case->poa_date?->toDateString()) }}" class="w-full rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
                <p class="text-xs text-gray-400 mt-1">تاريخ توثيق الوكالة</p>
            </div>
            <div class="sm:col-span-2">
                <input type="text" name="poa_city" placeholder="مثال: الرياض" value="{{ old('poa_city', $case->poa_city) }}" class="w-full rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
                <p class="text-xs text-gray-400 mt-1">مدينة كاتب العدل الذي وثّق الوكالة.</p>
            </div>
            <button type="submit" class="sm:col-span-2 bg-brand-600 hover:bg-brand-700 text-white rounded-full py-2.5 text-sm font-semibold transition-colors">حفظ بيانات المستند</button>
        </form>
    </div>

    <div class="flex gap-2 flex-wrap">
        <template x-for="key in Object.keys(labels)" :key="key">
            <button type="button" x-on:click="docTab = key"
                :class="docTab === key ? 'bg-brand-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'"
                class="px-4 py-2 rounded-full text-sm font-medium transition-colors"
                x-text="labels[key]"></button>
        </template>
    </div>

    <div class="flex items-center gap-3">
        <button type="button"
            x-on:click="window.LegalDocuments.downloadPdf(document.getElementById('legal-paper-{{ $case->id }}'), (labels[docTab] || 'مستند') + ' — {{ $case->debtor_name ?: $case->title }}.pdf')"
            class="bg-brand-600 hover:bg-brand-700 text-white rounded-full px-5 py-2 text-sm font-semibold transition-colors">
            تحميل PDF
        </button>
        <button type="button"
            x-on:click="window.LegalDocuments.downloadDocx(documents[docTab], '{{ addslashes($case->debtor_name ?: $case->title) }}', (labels[docTab] || 'مستند') + '.docx')"
            class="bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 rounded-full px-5 py-2 text-sm font-semibold transition-colors">
            تحميل Word
        </button>
    </div>

    <div class="overflow-x-auto pb-4">
        <div id="legal-paper-{{ $case->id }}" class="bg-white shadow-lg mx-auto relative" style="width: 794px; min-height: 500px; padding: 72px 80px;">
            <div class="absolute top-0 right-0 left-0 h-1.5" style="background: linear-gradient(90deg, #16653d 0%, #b8860b 50%, #16653d 100%);"></div>

            <div class="text-center border-b border-gray-200 pb-4 mb-7 pt-2">
                <p class="text-[11px] text-gray-500 font-medium mb-1">المملكة العربية السعودية</p>
                <p class="font-bold text-gray-900">{{ $case->debtor_name ?: 'اسم الشركة' }}</p>
                <p class="text-xs text-gray-400 mt-1" x-text="labels[docTab]"></p>
            </div>

            <div class="text-[13px] leading-loose text-gray-800" style="white-space: pre-line;" x-text="documents[docTab]"></div>

            <div class="grid sm:grid-cols-2 gap-8 mt-16 pt-8 border-t border-gray-100" x-show="['claim', 'resolution', 'poa'].includes(docTab)">
                <div class="text-center">
                    <p class="text-xs text-gray-400 mb-2">توقيع المحامي الوكيل</p>
                    @if ($case->lawyer_signature_data)
                        <img src="{{ $case->lawyer_signature_data }}" alt="توقيع المحامي" class="mx-auto h-16">
                    @else
                        <div class="h-16 border-b border-gray-300 w-40 mx-auto"></div>
                    @endif
                </div>
                <div class="text-center">
                    <p class="text-xs text-gray-400 mb-2">توقيع الممثل النظامي</p>
                    @if ($case->representative_signature_data)
                        <img src="{{ $case->representative_signature_data }}" alt="توقيع الممثل" class="mx-auto h-16">
                    @else
                        <div class="h-16 border-b border-gray-300 w-40 mx-auto"></div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- التقاط التوقيعات — Canvas حقيقي، بلا أي ادّعاء تحقق هوية حكومي --}}
    <div class="grid sm:grid-cols-2 gap-6">
        @foreach (['lawyer' => 'توقيع المحامي الوكيل', 'representative' => 'توقيع الممثل النظامي'] as $role => $label)
            <div class="bg-white border border-gray-100 rounded-2xl p-6" data-signature-card="{{ $role }}" x-data="{ redrawing: {{ $case->{$role.'_signature_data'} ? 'false' : 'true' }}, pad: null }">
                <h3 class="font-bold text-gray-900 mb-3 text-sm">{{ $label }}</h3>

                <template x-if="!redrawing">
                    <div>
                        @if ($case->{$role.'_signature_data'})
                            <img src="{{ $case->{$role.'_signature_data'} }}" alt="{{ $label }}" class="border border-gray-100 rounded-lg mb-3 h-24">
                        @endif
                        <button type="button" x-on:click="redrawing = true" class="text-brand-700 text-sm font-medium hover:underline">إعادة الرسم</button>
                    </div>
                </template>

                <template x-if="redrawing">
                    <div>
                        <canvas
                            x-init="pad = await window.LegalDocuments.attachSignaturePad($el)"
                            width="400" height="150"
                            class="border border-gray-200 rounded-lg touch-none bg-white max-w-full"
                        ></canvas>
                        <div class="flex items-center gap-3 mt-3">
                            <button type="button" x-on:click="pad.clear()" class="text-gray-500 text-sm hover:text-gray-700">مسح</button>
                            <button type="button"
                                x-on:click="
                                    if (pad.isEmpty()) { alert('ارسم توقيعك أولاً.'); return; }
                                    window.LegalDocuments.saveSignature('{{ route('bankruptcy-tech.cases.signature.update', $case) }}', '{{ $role }}', pad.toDataURL('image/png'))
                                        .then(() => window.location.reload())
                                        .catch(err => alert(err.message));
                                "
                                class="bg-brand-600 hover:bg-brand-700 text-white rounded-full px-4 py-1.5 text-sm font-semibold transition-colors">
                                حفظ التوقيع
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        @endforeach
    </div>
</div>
