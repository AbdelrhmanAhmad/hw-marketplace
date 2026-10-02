<x-platform-layout>
    <div class="bg-gradient-to-l from-forest to-brand-700 text-white">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <h1 class="text-2xl font-bold">فرصة تدريب جديدة</h1>
            <p class="text-brand-50 text-sm mt-1">تظهر مباشرة للطلاب بمجرد النشر — بلا مراجعة إدارية.</p>
        </div>
    </div>

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        @if ($errors->any())
            <div class="mb-6 bg-red-50 border border-red-100 text-red-700 rounded-2xl px-5 py-4 text-sm">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('internships.dashboard.store') }}" class="bg-white border border-gray-100 rounded-2xl p-8 space-y-6">
            @csrf

            <div>
                <label for="category" class="block text-sm font-semibold text-gray-700 mb-2">التصنيف</label>
                <select name="category" id="category" class="w-full rounded-xl border-gray-200">
                    @foreach (['قانوني', 'مالي', 'محاسبي', 'عام'] as $cat)
                        <option value="{{ $cat }}" @selected(old('category') === $cat)>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="title" class="block text-sm font-semibold text-gray-700 mb-2">عنوان الفرصة</label>
                <input type="text" name="title" id="title" value="{{ old('title') }}" maxlength="150" required class="w-full rounded-xl border-gray-200" placeholder="مثال: تدريب تعاوني بقسم الاستشارات القانونية">
            </div>

            <div>
                <label for="description" class="block text-sm font-semibold text-gray-700 mb-2">الوصف</label>
                <textarea name="description" id="description" rows="6" maxlength="5000" required class="w-full rounded-xl border-gray-200" placeholder="عرّف بمهام التدريب والمهارات المطلوبة...">{{ old('description') }}</textarea>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label for="location" class="block text-sm font-semibold text-gray-700 mb-2">الموقع (اختياري)</label>
                    <input type="text" name="location" id="location" value="{{ old('location') }}" maxlength="100" class="w-full rounded-xl border-gray-200" placeholder="مثال: الرياض، أو عن بُعد">
                </div>
                <div>
                    <label for="duration" class="block text-sm font-semibold text-gray-700 mb-2">المدة (اختياري)</label>
                    <input type="text" name="duration" id="duration" value="{{ old('duration') }}" maxlength="100" class="w-full rounded-xl border-gray-200" placeholder="مثال: 3 أشهر">
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white rounded-full px-6 py-2.5 text-sm font-semibold transition-colors">نشر الفرصة</button>
                <a href="{{ route('internships.dashboard.index') }}" class="text-sm text-gray-500 hover:text-gray-700">إلغاء</a>
            </div>
        </form>
    </div>
</x-platform-layout>
