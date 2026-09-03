<x-platform-layout>
    <div class="bg-gradient-to-l from-forest to-brand-700 text-white">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <a href="{{ route('bankruptcy-tech.cases.index') }}" class="text-xs text-brand-100 hover:text-white transition-colors mb-4 inline-block">← رجوع للقضايا</a>

            <div class="flex items-start justify-between flex-wrap gap-4">
                <div>
                    <p class="text-xs text-brand-100 mb-1 font-mono">{{ $case->case_number }}</p>
                    <h1 class="text-2xl font-bold mb-2">{{ $case->title }}</h1>
                    <div class="flex items-center gap-2">
                        <x-bankruptcy-tech.case-status-badge :status="$case->status" />
                        <span class="text-xs text-brand-100">{{ $case->organization?->name ?? 'قضية شخصية' }}</span>
                    </div>
                </div>

                @php
                    $profileComplete = $case->debtor_name && $case->cr_number && $case->attorney_name && $case->attorney_license;
                    $financialComplete = $case->creditors->isNotEmpty();
                    $milestonesDone = collect([$profileComplete, $financialComplete, $isReadyForRecommendation])->filter()->count();
                    $overallCompletion = (int) round($milestonesDone / 3 * 100);
                @endphp
                <div class="text-left">
                    <p class="text-xs text-brand-100 mb-1">اكتمال الملف</p>
                    <div class="flex items-center gap-2">
                        <div class="h-2 w-28 bg-white/20 rounded-full overflow-hidden">
                            <div class="h-full bg-white rounded-full" style="width: {{ $overallCompletion }}%"></div>
                        </div>
                        <span class="text-sm font-semibold">{{ $overallCompletion }}٪</span>
                    </div>
                </div>

                @if ($canManage && ! $case->isClosed())
                    <form action="{{ route('bankruptcy-tech.cases.status.update', $case) }}" method="POST" class="flex items-center gap-2">
                        @csrf
                        @method('PATCH')
                        <select name="status" class="rounded-xl border-0 text-sm text-gray-900 focus:ring-2 focus:ring-white">
                            @foreach (['draft' => 'مسودة', 'preparing' => 'قيد الإعداد', 'submitted' => 'مُقدَّمة للمحكمة', 'decided' => 'صدر قرار', 'closed' => 'إغلاق القضية'] as $value => $label)
                                <option value="{{ $value }}" @selected($case->status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="bg-white text-brand-700 hover:bg-brand-50 rounded-full px-5 py-2 text-sm font-semibold transition-colors">
                            تحديث الحالة
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    @php
        // إعادة هيكلة التنقّل (جولة 2 — تقليل "الضياع"، لا تكرار الترقيع):
        // مجموعات مرقَّمة بترتيب سير العمل الفعلي (بيانات ← وضع مالي ← تشخيص
        // يعتمد على الوضع المالي ← مستندات ← متابعة الإجراء ← فريق ← سجل)،
        // بعلامة ✓ للمراحل الثلاث الأولى (لها حالة "اكتمال" حقيقية وواضحة).
        // كل التبويبات مرئية دائمًا (flex-wrap بدل التمرير) — لا شيء يختفي.
        $sections = [
            'profile' => ['label' => 'الملف الأساسي', 'done' => $profileComplete, 'tabs' => [
                'overview' => 'نظرة عامة',
            ]],
            'financial' => ['label' => 'الوضع المالي', 'done' => $financialComplete, 'tabs' => [
                'creditors' => 'الدائنون ('.$case->creditors->count().')',
                'assets' => 'الأصول ('.$case->assets->count().')',
                'employees' => 'الموظفون ('.$case->employees->count().')',
            ]],
            'diagnosis' => ['label' => 'التشخيص والتوصية', 'done' => $isReadyForRecommendation, 'tabs' => [
                'wizard' => 'معالج التشخيص',
            ]],
            'documents' => ['label' => 'المستندات', 'done' => null, 'tabs' => [
                'legal-documents' => 'المستندات القانونية',
                'documents' => 'المستندات المرفوعة ('.$case->documents->count().')',
            ]],
            'procedure' => ['label' => 'سير الإجراء', 'done' => null, 'tabs' => [
                'checklists' => 'القوائم التنظيمية',
                'timeline' => 'الجدول الزمني',
                'hearings' => 'الجلسات ('.$case->hearings->count().')',
                'procedures' => 'الإجراءات ('.$case->procedures->count().')',
            ]],
            'team' => ['label' => 'الفريق والوصول', 'done' => null, 'tabs' => [
                'parties' => 'الأطراف ('.$case->parties->count().')',
                'client' => 'بوابة العميل',
            ]],
            'log' => ['label' => 'الملاحظات والسجل', 'done' => null, 'tabs' => [
                'notes' => 'الملاحظات ('.$case->notes->count().')',
                'activity' => 'سجل الأحداث',
            ]],
        ];
        $tabToGroup = [];
        foreach ($sections as $groupKey => $group) {
            foreach ($group['tabs'] as $tabKey => $label) {
                $tabToGroup[$tabKey] = $groupKey;
            }
        }

        // اقتراح "الخطوة التالية" — بدل ترك المستخدم يكتشف بنفسه وش ناقص
        // وبأي تبويب، خصوصًا إن بيانات مستند واحد (صحيفة الدعوى مثلًا) تعتمد
        // على حقول موزّعة بين تبويبين مختلفين (نظرة عامة + المستندات القانونية).
        $nextStep = null;
        if (! $case->debtor_name || ! $case->cr_number || ! $case->attorney_name) {
            $nextStep = ['label' => 'أكمل بيانات المدين والمحامي الأساسية — تُستخدَم بالمستندات القانونية وحساب النواقص', 'tab' => 'overview'];
        } elseif (! $isReadyForRecommendation) {
            $nextStep = ['label' => 'أكمل معالج التشخيص لعرض التوصية القانونية', 'tab' => 'wizard'];
        } elseif ($case->creditors->isEmpty()) {
            $nextStep = ['label' => 'أضف الدائنين لحساب إجمالي الديون', 'tab' => 'creditors'];
        } elseif ($case->assets->isEmpty() && $case->has_assets !== 'no') {
            $nextStep = ['label' => 'أضف الأصول، أو أكّد بمعالج التشخيص عدم وجود أصول', 'tab' => 'assets'];
        }
    @endphp

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10" x-data="{ tab: window.location.hash ? window.location.hash.slice(1) : 'overview', groupOf: @js($tabToGroup), get section() { return this.groupOf[this.tab] || 'profile' } }">
        @if (session('status'))
            <div class="mb-6 bg-brand-50 border border-brand-100 text-brand-700 rounded-2xl px-5 py-4 text-sm font-medium">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-6 bg-red-50 border border-red-100 text-red-700 rounded-2xl px-5 py-4 text-sm font-medium">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        @if ($nextStep)
            <div class="mb-8 bg-gold-50 border border-gold-100 text-gold-800 rounded-2xl px-5 py-4 text-sm font-medium flex items-center justify-between gap-4 flex-wrap">
                <span>الخطوة التالية: {{ $nextStep['label'] }}</span>
                <button type="button"
                    x-on:click="tab = '{{ $nextStep['tab'] }}'; history.replaceState(null, '', '#{{ $nextStep['tab'] }}')"
                    class="bg-white text-gold-800 border border-gold-200 rounded-full px-4 py-1.5 text-xs font-semibold hover:bg-gold-100 transition-colors shrink-0">
                    الانتقال إليها ←
                </button>
            </div>
        @endif

        {{-- المجموعات الرئيسية — مرقَّمة بترتيب سير العمل، بعلامة ✓ لما له حالة اكتمال واضحة --}}
        <div class="flex gap-2 flex-wrap mb-3">
            @foreach ($sections as $groupKey => $group)
                <button type="button"
                    x-on:click="tab = '{{ array_key_first($group['tabs']) }}'; history.replaceState(null, '', '#' + tab)"
                    :class="section === '{{ $groupKey }}' ? 'bg-brand-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'"
                    class="px-4 py-2 rounded-full text-sm font-semibold transition-colors flex items-center gap-1.5"
                >
                    @if ($group['done'] === true)
                        <span class="inline-flex items-center justify-center h-4 w-4 rounded-full text-[10px] shrink-0 bg-green-500 text-white">✓</span>
                    @else
                        <span class="inline-flex items-center justify-center h-4 w-4 rounded-full text-[10px] shrink-0"
                            :class="section === '{{ $groupKey }}' ? 'bg-white/25 text-white' : 'bg-gray-100 text-gray-500'"
                        >{{ $loop->iteration }}</span>
                    @endif
                    {{ $group['label'] }}
                </button>
            @endforeach
        </div>

        {{-- التبويبات الفرعية — فقط لمجموعة فيها أكثر من قسم واحد، وفقط للمجموعة النشطة، كلها مرئية دومًا (بلا تمرير) --}}
        <div class="flex gap-1 border-b border-gray-100 mb-8 flex-wrap"
             x-show="{{ collect($sections)->filter(fn ($g) => count($g['tabs']) > 1)->keys()->map(fn ($k) => "section === '{$k}'")->implode(' || ') ?: 'false' }}"
        >
            @foreach ($sections as $groupKey => $group)
                @foreach ($group['tabs'] as $tabKey => $label)
                    <button
                        type="button"
                        x-show="section === '{{ $groupKey }}'"
                        x-on:click="tab = '{{ $tabKey }}'; history.replaceState(null, '', '#{{ $tabKey }}')"
                        :class="tab === '{{ $tabKey }}' ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="px-4 py-3 text-sm font-medium border-b-2 whitespace-nowrap transition-colors"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            @endforeach
        </div>

        {{-- نظرة عامة --}}
        <div x-show="tab === 'overview'" x-cloak class="space-y-6">
            <div class="bg-white border border-gray-100 rounded-2xl p-7">
                <h2 class="font-bold text-gray-900 mb-3">الوصف</h2>
                <p class="text-gray-600 leading-relaxed">{{ $case->description ?: 'لا يوجد وصف مُضاف لهذي القضية.' }}</p>
                <dl class="grid sm:grid-cols-2 gap-5 mt-8 text-sm">
                    <div><dt class="text-gray-400 mb-1">أُنشئت بواسطة</dt><dd class="text-gray-800 font-medium">{{ $case->creator->name }}</dd></div>
                    <div><dt class="text-gray-400 mb-1">تاريخ الفتح</dt><dd class="text-gray-800 font-medium">{{ $case->opened_at?->translatedFormat('d F Y') }}</dd></div>
                    @if ($case->closed_at)
                        <div><dt class="text-gray-400 mb-1">تاريخ الإغلاق</dt><dd class="text-gray-800 font-medium">{{ $case->closed_at->translatedFormat('d F Y') }}</dd></div>
                    @endif
                    <div><dt class="text-gray-400 mb-1">إجمالي الديون</dt><dd class="text-gray-800 font-medium">{{ number_format($case->total_debts, 2) }} ر.س</dd></div>
                    <div><dt class="text-gray-400 mb-1">إجمالي الأصول</dt><dd class="text-gray-800 font-medium">{{ number_format($case->total_assets, 2) }} ر.س</dd></div>
                </dl>
            </div>

            <form action="{{ route('bankruptcy-tech.cases.profile.update', $case) }}" method="POST" class="bg-white border border-gray-100 rounded-2xl p-7">
                @csrf
                @method('PATCH')
                <input type="hidden" name="_tab" value="overview">
                <h2 class="font-bold text-gray-900 mb-1">بيانات الملف</h2>
                <p class="text-xs text-gray-400 mb-5">الحقول المُعلَّمة بـ<span class="text-red-500">*</span> ضرورية لتوليد المستندات القانونية بدقة — يمكنك تعبئتها تدريجيًا، الحفظ يعمل بأي وقت.</p>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <input type="text" name="debtor_name" placeholder="اسم المدين *" value="{{ old('debtor_name', $case->debtor_name) }}" class="w-full rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
                        <p class="text-xs text-gray-400 mt-1">الاسم النظامي الكامل كما بالسجل التجاري.</p>
                    </div>
                    <input type="text" name="legal_form" placeholder="الشكل النظامي" value="{{ old('legal_form', $case->legal_form) }}" class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
                    <div>
                        <input type="text" name="cr_number" placeholder="رقم السجل التجاري *" maxlength="10" value="{{ old('cr_number', $case->cr_number) }}" class="w-full rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
                        <p class="text-xs text-gray-400 mt-1">10 أرقام بالضبط، بدون مسافات أو رموز.</p>
                    </div>
                    <input type="text" name="cr_city" placeholder="مدينة السجل التجاري" value="{{ old('cr_city', $case->cr_city) }}" class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
                    <input type="text" name="court_city" placeholder="مدينة المحكمة" value="{{ old('court_city', $case->court_city) }}" class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
                    <input type="text" name="court_case_number" placeholder="رقم القضية بالمحكمة (بعد التقديم)" value="{{ old('court_case_number', $case->court_case_number) }}" class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
                    <div>
                        <input type="date" name="submission_date" value="{{ old('submission_date', $case->submission_date?->toDateString()) }}" class="w-full rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
                        <p class="text-xs text-gray-400 mt-1">تاريخ تقديم الطلب فعليًا للمحكمة — يُستخدَم لحساب الجدول الزمني.</p>
                    </div>
                    <input type="text" name="representative_name" placeholder="اسم الممثل النظامي" value="{{ old('representative_name', $case->representative_name) }}" class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
                    <input type="text" name="representative_title" placeholder="صفة الممثل" value="{{ old('representative_title', $case->representative_title) }}" class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
                    <input type="text" name="representative_id" placeholder="رقم هوية الممثل" value="{{ old('representative_id', $case->representative_id) }}" class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
                    <div>
                        <input type="text" name="attorney_name" placeholder="اسم المحامي الوكيل *" value="{{ old('attorney_name', $case->attorney_name) }}" class="w-full rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
                        <p class="text-xs text-gray-400 mt-1">الاسم الكامل كما بترخيص هيئة المحامين.</p>
                    </div>
                    <div>
                        <input type="text" name="attorney_license" placeholder="رقم رخصة المحاماة *" value="{{ old('attorney_license', $case->attorney_license) }}" class="w-full rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
                        <p class="text-xs text-gray-400 mt-1">مثال: 41/892</p>
                    </div>
                    <input type="text" name="trustee_name" placeholder="أمين التفليسة (بعد التعيين)" value="{{ old('trustee_name', $case->trustee_name) }}" class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500 sm:col-span-2">
                </div>
                <button type="submit" class="mt-5 bg-brand-600 hover:bg-brand-700 text-white rounded-full px-6 py-2.5 text-sm font-semibold transition-colors">حفظ بيانات الملف</button>
            </form>
        </div>

        @include('bankruptcy-tech.partials._wizard')
        @include('bankruptcy-tech.partials._legal-documents')
        @include('bankruptcy-tech.partials._creditors')
        @include('bankruptcy-tech.partials._assets')
        @include('bankruptcy-tech.partials._employees')
        @include('bankruptcy-tech.partials._checklists')
        @include('bankruptcy-tech.partials._timeline')
        @include('bankruptcy-tech.partials._hearings')
        @include('bankruptcy-tech.partials._client')

        {{-- الأطراف --}}
        <div x-show="tab === 'parties'" x-cloak class="space-y-6">
            <form action="{{ route('bankruptcy-tech.cases.parties.store', $case) }}" method="POST" class="bg-white border border-gray-100 rounded-2xl p-6 grid sm:grid-cols-2 gap-4">
                @csrf
                <input type="text" name="name" placeholder="اسم الطرف" required class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500" value="{{ old('name') }}">
                <select name="role" required class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
                    <option value="">صفة الطرف…</option>
                    <option value="debtor">مدين</option>
                    <option value="creditor">دائن</option>
                    <option value="trustee">أمين تفليسة</option>
                    <option value="other">طرف آخر</option>
                </select>
                <input type="text" name="identifier" placeholder="رقم هوية/سجل تجاري (اختياري)" class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
                <input type="text" name="contact" placeholder="بيانات التواصل (اختياري)" class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
                <button type="submit" class="sm:col-span-2 bg-brand-600 hover:bg-brand-700 text-white rounded-full py-2.5 text-sm font-semibold transition-colors">إضافة طرف</button>
            </form>

            @forelse ($case->parties as $party)
                <div class="bg-white border border-gray-100 rounded-2xl p-5 flex items-center justify-between">
                    <div>
                        <p class="font-semibold text-gray-900">{{ $party->name }}</p>
                        <p class="text-xs text-gray-500">{{ $party->roleLabel() }} @if($party->identifier) · {{ $party->identifier }} @endif</p>
                    </div>
                </div>
            @empty
                <div class="text-center text-gray-400 py-10">لا أطراف مُضافة بعد.</div>
            @endforelse
        </div>

        {{-- الإجراءات --}}
        <div x-show="tab === 'procedures'" x-cloak class="space-y-6">
            <form action="{{ route('bankruptcy-tech.cases.procedures.store', $case) }}" method="POST" class="bg-white border border-gray-100 rounded-2xl p-6 grid sm:grid-cols-2 gap-4">
                @csrf
                <input type="text" name="title" placeholder="عنوان الإجراء" required class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500 sm:col-span-2" value="{{ old('title') }}">
                <input type="date" name="due_date" class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
                <div></div>
                <textarea name="description" rows="2" placeholder="تفاصيل (اختياري)" class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500 sm:col-span-2"></textarea>
                <button type="submit" class="sm:col-span-2 bg-brand-600 hover:bg-brand-700 text-white rounded-full py-2.5 text-sm font-semibold transition-colors">إضافة إجراء</button>
            </form>

            @forelse ($case->procedures as $procedure)
                <div class="bg-white border border-gray-100 rounded-2xl p-5">
                    <div class="flex items-center justify-between gap-4 flex-wrap">
                        <div>
                            <p class="font-semibold text-gray-900">{{ $procedure->title }}</p>
                            @if ($procedure->description)
                                <p class="text-xs text-gray-500 mt-1">{{ $procedure->description }}</p>
                            @endif
                            @if ($procedure->due_date)
                                <p class="text-xs text-gray-400 mt-1">الاستحقاق: {{ $procedure->due_date->translatedFormat('d F Y') }}</p>
                            @endif
                        </div>
                        <form action="{{ route('bankruptcy-tech.cases.procedures.status.update', [$case, $procedure]) }}" method="POST" class="flex items-center gap-2">
                            @csrf
                            @method('PATCH')
                            <select name="status" onchange="this.form.submit()" class="rounded-xl border-gray-200 text-sm focus:ring-brand-500 focus:border-brand-500">
                                <option value="pending" @selected($procedure->status === 'pending')>قيد الانتظار</option>
                                <option value="in_progress" @selected($procedure->status === 'in_progress')>قيد التنفيذ</option>
                                <option value="completed" @selected($procedure->status === 'completed')>مكتمل</option>
                            </select>
                        </form>
                    </div>
                </div>
            @empty
                <div class="text-center text-gray-400 py-10">لا إجراءات مُضافة بعد.</div>
            @endforelse
        </div>

        {{-- المستندات --}}
        <div x-show="tab === 'documents'" x-cloak class="space-y-6">
            <form action="{{ route('bankruptcy-tech.cases.documents.store', $case) }}" method="POST" enctype="multipart/form-data" class="bg-white border border-gray-100 rounded-2xl p-6 grid sm:grid-cols-2 gap-4">
                @csrf
                <input type="text" name="title" placeholder="عنوان المستند" required class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500" value="{{ old('title') }}">
                <input type="file" name="file" required class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500 text-sm">
                <p class="sm:col-span-2 text-xs text-gray-400">PDF، صور، Word — حتى 10 ميجابايت.</p>
                <button type="submit" class="sm:col-span-2 bg-brand-600 hover:bg-brand-700 text-white rounded-full py-2.5 text-sm font-semibold transition-colors">رفع المستند</button>
            </form>

            @forelse ($case->documents as $document)
                <div class="bg-white border border-gray-100 rounded-2xl p-5 flex items-center justify-between">
                    <div>
                        <p class="font-semibold text-gray-900">{{ $document->title }}</p>
                        <p class="text-xs text-gray-500">{{ $document->original_filename }} · {{ $document->humanSize() }} · {{ $document->uploadedBy->name }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('bankruptcy-tech.cases.documents.download', [$case, $document]) }}" class="text-brand-700 text-sm font-medium hover:underline">تنزيل</a>
                        @if ($canManage)
                            <form action="{{ route('bankruptcy-tech.cases.documents.destroy', [$case, $document]) }}" method="POST" onsubmit="return confirm('تأكيد حذف المستند؟')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-gray-400 hover:text-red-600 text-sm transition-colors">حذف</button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="text-center text-gray-400 py-10">لا مستندات مرفوعة بعد.</div>
            @endforelse
        </div>

        {{-- الملاحظات --}}
        <div x-show="tab === 'notes'" x-cloak class="space-y-6">
            <form action="{{ route('bankruptcy-tech.cases.notes.store', $case) }}" method="POST" class="bg-white border border-gray-100 rounded-2xl p-6">
                @csrf
                <textarea name="body" rows="3" required placeholder="أضف ملاحظة…" class="w-full rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500 mb-3">{{ old('body') }}</textarea>
                <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white rounded-full px-6 py-2.5 text-sm font-semibold transition-colors">إضافة ملاحظة</button>
            </form>

            @forelse ($case->notes->sortByDesc('created_at') as $note)
                <div class="bg-white border border-gray-100 rounded-2xl p-5">
                    <p class="text-gray-700 leading-relaxed whitespace-pre-line">{{ $note->body }}</p>
                    <p class="text-xs text-gray-400 mt-3">{{ $note->user->name }} · {{ $note->created_at->diffForHumans() }}</p>
                </div>
            @empty
                <div class="text-center text-gray-400 py-10">لا ملاحظات بعد.</div>
            @endforelse
        </div>

        {{-- سجل الأحداث (AuditLog) — مختلف عن تبويب "الجدول الزمني" القانوني أعلاه --}}
        <div x-show="tab === 'activity'" x-cloak class="bg-white border border-gray-100 rounded-2xl p-7">
            @forelse ($timeline as $entry)
                <div class="flex items-start gap-4 pb-6 last:pb-0">
                    <span class="h-2.5 w-2.5 rounded-full bg-brand-600 mt-1.5 shrink-0"></span>
                    <div>
                        <p class="text-sm text-gray-800">{{ \App\Support\BankruptcyCaseTimeline::describe($entry) }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $entry->actor->name ?? 'النظام' }} · {{ $entry->created_at->diffForHumans() }}</p>
                    </div>
                </div>
            @empty
                <div class="text-center text-gray-400 py-6">لا أحداث مسجَّلة بعد.</div>
            @endforelse
        </div>
    </div>
</x-platform-layout>
