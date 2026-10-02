<x-platform-layout>
    <div class="bg-gradient-to-l from-maroon-800 via-maroon-700 to-gold-700 text-white">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <h1 class="font-markazi font-bold text-3xl">إعلان خدمة جديد</h1>
            <p class="text-cream/80 text-sm mt-1">يظهر مباشرة لأي زائر بمجتمع الخدمات — بلا مراجعة إدارية.</p>
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

        <form method="POST" action="{{ route('community.dashboard.store') }}" class="bg-white border border-maroon-100 rounded-2xl p-8 space-y-6">
            @csrf

            <div>
                <label for="category" class="block text-sm font-semibold text-gray-700 mb-2">التصنيف</label>
                <select name="category" id="category" class="w-full rounded-xl border-gray-200 focus:border-maroon-400 focus:ring-maroon-400">
                    @foreach (['قانوني', 'مالي', 'محاسبي', 'عام'] as $cat)
                        <option value="{{ $cat }}" @selected(old('category') === $cat)>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="title" class="block text-sm font-semibold text-gray-700 mb-2">عنوان الخدمة</label>
                <input type="text" name="title" id="title" value="{{ old('title') }}" maxlength="150" required class="w-full rounded-xl border-gray-200 focus:border-maroon-400 focus:ring-maroon-400" placeholder="مثال: استشارات تأسيس شركات">
            </div>

            <div>
                <label for="description" class="block text-sm font-semibold text-gray-700 mb-2">وصف الخدمة</label>
                <textarea name="description" id="description" rows="6" maxlength="5000" required class="w-full rounded-xl border-gray-200 focus:border-maroon-400 focus:ring-maroon-400" placeholder="عرّف بخبرتك والخدمة التي تقدّمها...">{{ old('description') }}</textarea>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label for="contact_method" class="block text-sm font-semibold text-gray-700 mb-2">وسيلة التواصل</label>
                    <select name="contact_method" id="contact_method" class="w-full rounded-xl border-gray-200 focus:border-maroon-400 focus:ring-maroon-400">
                        <option value="phone" @selected(old('contact_method', 'phone') === 'phone')>هاتف</option>
                        <option value="whatsapp" @selected(old('contact_method') === 'whatsapp')>واتساب</option>
                        <option value="email" @selected(old('contact_method') === 'email')>بريد إلكتروني</option>
                    </select>
                </div>
                <div>
                    <label for="contact_value" class="block text-sm font-semibold text-gray-700 mb-2">بيانات التواصل</label>
                    <input type="text" name="contact_value" id="contact_value" value="{{ old('contact_value') }}" maxlength="150" required dir="ltr" class="w-full rounded-xl border-gray-200 focus:border-maroon-400 focus:ring-maroon-400" placeholder="05xxxxxxxx">
                </div>
            </div>
            <p class="text-xs text-gray-400 -mt-3">تظهر بيانات التواصل هذي مباشرة لأي زائر يفتح إعلانك.</p>

            <div class="flex items-center gap-3">
                <button type="submit" class="bg-maroon-600 hover:bg-maroon-700 text-white rounded-full px-6 py-2.5 text-sm font-semibold transition-colors">نشر الإعلان</button>
                <a href="{{ route('community.dashboard.index') }}" class="text-sm text-gray-500 hover:text-gray-700">إلغاء</a>
            </div>
        </form>
    </div>
</x-platform-layout>
