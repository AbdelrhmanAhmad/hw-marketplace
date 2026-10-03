<x-platform-layout>
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
        <a href="{{ route('bankruptcy-tech.cases.index') }}" class="text-xs text-gray-400 hover:text-gray-600 transition-colors mb-6 inline-block">← رجوع للقضايا</a>

        <h1 class="text-2xl font-bold text-gray-900 mb-1">قضية جديدة</h1>
        <p class="text-sm text-gray-500 mb-8">
            ستُنشأ باسم: <span class="font-medium text-gray-700">{{ $activeOrganization?->name ?? 'مساحتك الشخصية' }}</span>
        </p>

        <form action="{{ route('bankruptcy-tech.cases.store') }}" method="POST" class="bg-white border border-gray-100 rounded-2xl shadow-sm p-7 space-y-5">
            @csrf

            <div>
                <label for="title" class="block text-sm font-medium text-gray-700 mb-1.5">عنوان القضية <span class="text-red-500">*</span></label>
                <input
                    type="text" id="title" name="title" value="{{ old('title') }}" required
                    class="w-full rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500"
                    placeholder="مثال: تصفية منشأة الفارس التجارية"
                >
                @error('title')
                    <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1.5">وصف مختصر</label>
                <textarea
                    id="description" name="description" rows="4"
                    class="w-full rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500"
                    placeholder="تفاصيل أولية عن القضية (اختياري)"
                >{{ old('description') }}</textarea>
                @error('description')
                    <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white rounded-full py-3 font-semibold shadow-sm hover:shadow transition-all">
                إنشاء القضية
            </button>
        </form>
    </div>
</x-platform-layout>
