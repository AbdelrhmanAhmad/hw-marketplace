<x-app-layout>
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <a href="{{ route('internships.index') }}" class="text-sm text-gray-500 hover:text-brand-700 transition-colors mb-6 inline-block">← رجوع لبوابة التدريب التعاوني</a>

        @if (session('status'))
            <div class="mb-6 bg-brand-50 border border-brand-100 text-brand-700 rounded-2xl px-5 py-4 text-sm font-medium">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-6 bg-red-50 border border-red-100 text-red-700 rounded-2xl px-5 py-4 text-sm">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="flex items-center gap-2 mb-4">
            <span class="text-xs bg-brand-50 text-brand-700 px-2.5 py-1 rounded-full">{{ $opportunity->category }}</span>
            @if ($opportunity->location)
                <span class="text-xs bg-gray-50 text-gray-500 px-2.5 py-1 rounded-full">{{ $opportunity->location }}</span>
            @endif
            @if ($opportunity->duration)
                <span class="text-xs bg-gray-50 text-gray-500 px-2.5 py-1 rounded-full">{{ $opportunity->duration }}</span>
            @endif
        </div>

        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-4">{{ $opportunity->title }}</h1>

        <div class="flex items-center gap-2 text-sm text-gray-500 mb-8 pb-8 border-b border-gray-100">
            <span class="font-medium text-gray-700">{{ $opportunity->user->name }}</span>
            <span>· {{ $opportunity->created_at->translatedFormat('d F Y') }}</span>
        </div>

        <div class="text-gray-700 leading-loose text-[15px] whitespace-pre-line mb-10">{{ $opportunity->description }}</div>

        <div class="bg-white border border-gray-100 rounded-2xl p-8">
            @if ($isOwner)
                <p class="text-sm text-gray-500">هذي فرصتك — تقدر تدير التقديمات من <a href="{{ route('internships.dashboard.show', $opportunity) }}" class="text-brand-700 hover:underline font-medium">لوحة فرصي</a>.</p>
            @elseif ($alreadyApplied)
                <p class="text-sm text-brand-700 font-medium">✓ قدَّمت على هذي الفرصة — بيتواصلون معك.</p>
            @elseif (auth()->check())
                <h2 class="font-bold text-gray-900 mb-1">التقديم على الفرصة</h2>
                <p class="text-sm text-gray-500 mb-6">المستندات التالية مطلوبة لأي تقديم تدريب تعاوني — PDF أو صورة، حتى 5 ميجا لكل ملف.</p>

                <form method="POST" action="{{ route('internships.apply', $opportunity) }}" enctype="multipart/form-data" class="space-y-5">
                    @csrf

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label for="cv" class="block text-sm font-semibold text-gray-700 mb-1.5">السيرة الذاتية <span class="text-red-500">*</span></label>
                            <input type="file" name="cv" id="cv" accept=".pdf,.jpg,.jpeg,.png" required class="w-full text-sm rounded-xl border-gray-200 file:me-3 file:rounded-full file:border-0 file:bg-brand-50 file:text-brand-700 file:px-3 file:py-1.5">
                        </div>
                        <div>
                            <label for="enrollment_letter" class="block text-sm font-semibold text-gray-700 mb-1.5">إفادة القيد الجامعي <span class="text-red-500">*</span></label>
                            <input type="file" name="enrollment_letter" id="enrollment_letter" accept=".pdf,.jpg,.jpeg,.png" required class="w-full text-sm rounded-xl border-gray-200 file:me-3 file:rounded-full file:border-0 file:bg-brand-50 file:text-brand-700 file:px-3 file:py-1.5">
                        </div>
                        <div>
                            <label for="transcript" class="block text-sm font-semibold text-gray-700 mb-1.5">كشف الدرجات (اختياري)</label>
                            <input type="file" name="transcript" id="transcript" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-sm rounded-xl border-gray-200 file:me-3 file:rounded-full file:border-0 file:bg-gray-50 file:text-gray-600 file:px-3 file:py-1.5">
                        </div>
                        <div>
                            <label for="national_id" class="block text-sm font-semibold text-gray-700 mb-1.5">صورة الهوية الوطنية (اختياري)</label>
                            <input type="file" name="national_id" id="national_id" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-sm rounded-xl border-gray-200 file:me-3 file:rounded-full file:border-0 file:bg-gray-50 file:text-gray-600 file:px-3 file:py-1.5">
                        </div>
                    </div>

                    <div>
                        <label for="message" class="block text-sm font-semibold text-gray-700 mb-1.5">رسالة (اختياري)</label>
                        <textarea name="message" id="message" rows="4" maxlength="1000" class="w-full rounded-xl border-gray-200" placeholder="عرّف بنفسك ولماذا تناسبك هذي الفرصة...">{{ old('message') }}</textarea>
                    </div>

                    <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white rounded-full px-6 py-2.5 text-sm font-semibold transition-colors">تقديم</button>
                </form>
            @else
                <p class="text-sm text-gray-600 mb-3">سجّل دخولك للتقديم على هذي الفرصة.</p>
                <a href="{{ route('login') }}" class="inline-block bg-brand-600 hover:bg-brand-700 text-white rounded-full px-6 py-2.5 text-sm font-semibold transition-colors">تسجيل الدخول</a>
            @endif
        </div>
    </div>
</x-app-layout>
