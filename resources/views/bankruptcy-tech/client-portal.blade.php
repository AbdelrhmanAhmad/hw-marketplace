<x-platform-layout>
    <div class="bg-gradient-to-l from-forest to-brand-700 text-white">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <p class="text-xs text-brand-100 mb-1">بوابة العميل</p>
            <h1 class="text-2xl font-bold mb-2">{{ $case->debtor_name ?: $case->title }}</h1>
            <div class="flex items-center gap-2">
                <x-bankruptcy-tech.case-status-badge :status="$case->status" />
                @if ($case->cr_number)
                    <span class="text-xs text-brand-100 font-mono">{{ $case->cr_number }}</span>
                @endif
            </div>
        </div>
    </div>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-6">
        @if (session('status'))
            <div class="bg-brand-50 border border-brand-100 text-brand-700 rounded-2xl px-5 py-4 text-sm font-medium">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="bg-red-50 border border-red-100 text-red-700 rounded-2xl px-5 py-4 text-sm font-medium">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="bg-white border border-gray-100 rounded-2xl p-7">
            <h2 class="font-bold text-gray-900 mb-3">ملخّص القضية</h2>
            <dl class="grid sm:grid-cols-2 gap-5 text-sm">
                <div><dt class="text-gray-400 mb-1">إجمالي الديون</dt><dd class="text-gray-800 font-medium">{{ number_format($case->total_debts, 2) }} ر.س</dd></div>
                <div><dt class="text-gray-400 mb-1">إجمالي الأصول</dt><dd class="text-gray-800 font-medium">{{ number_format($case->total_assets, 2) }} ر.س</dd></div>
                @if ($case->court_case_number)
                    <div><dt class="text-gray-400 mb-1">رقم القضية بالمحكمة</dt><dd class="text-gray-800 font-medium font-mono">{{ $case->court_case_number }}</dd></div>
                @endif
                @if ($case->court_city)
                    <div><dt class="text-gray-400 mb-1">المحكمة</dt><dd class="text-gray-800 font-medium">{{ $case->court_city }}</dd></div>
                @endif
            </dl>
        </div>

        <div class="bg-white border border-gray-100 rounded-2xl p-6">
            <h2 class="font-bold text-gray-900 mb-4">المستندات</h2>

            <form action="{{ route('client-portal.cases.documents.store', $case) }}" method="POST" enctype="multipart/form-data" class="grid sm:grid-cols-2 gap-4 mb-6">
                @csrf
                <input type="text" name="title" placeholder="عنوان المستند" required class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500" value="{{ old('title') }}">
                <input type="file" name="file" required class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500 text-sm">
                <p class="sm:col-span-2 text-xs text-gray-400">PDF، صور، Word — حتى 10 ميجابايت.</p>
                <button type="submit" class="sm:col-span-2 bg-brand-600 hover:bg-brand-700 text-white rounded-full py-2.5 text-sm font-semibold transition-colors">رفع مستند</button>
            </form>

            @forelse ($case->documents as $document)
                <div class="flex items-center justify-between py-3 border-t border-gray-50 first:border-0">
                    <div>
                        <p class="font-medium text-gray-900 text-sm">{{ $document->title }}</p>
                        <p class="text-xs text-gray-500">{{ $document->original_filename }} · {{ $document->humanSize() }}</p>
                    </div>
                    <a href="{{ route('client-portal.cases.documents.download', [$case, $document]) }}" class="text-brand-700 text-sm font-medium hover:underline">تنزيل</a>
                </div>
            @empty
                <p class="text-center text-gray-400 py-6 text-sm">لا مستندات بعد.</p>
            @endforelse
        </div>
    </div>
</x-platform-layout>
