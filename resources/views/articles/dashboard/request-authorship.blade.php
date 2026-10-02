<x-platform-layout>
    <div class="bg-gradient-to-l from-forest to-brand-700 text-white">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <a href="{{ route('articles.dashboard.index') }}" class="text-xs text-brand-100 hover:text-white transition-colors mb-4 inline-block">← رجوع</a>
            <h1 class="text-2xl font-bold">طلب أن تصبح مؤلفًا</h1>
            <p class="text-brand-50 text-sm mt-1">عرّف بنفسك وتخصصك — فريق المنصة يراجع الطلب قبل الموافقة.</p>
        </div>
    </div>

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        @if ($errors->any())
            <div class="mb-6 bg-red-50 border border-red-100 text-red-700 rounded-2xl px-5 py-4 text-sm font-medium">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form action="{{ route('articles.dashboard.author.store') }}" method="POST" class="bg-white border border-gray-100 rounded-2xl p-7 space-y-4">
            @csrf
            <div>
                <input type="text" name="expertise" placeholder="التخصص (مثال: محامٍ متخصص بقانون الشركات) *" value="{{ old('expertise') }}" class="w-full rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
            </div>
            <div>
                <textarea name="bio" rows="5" placeholder="نبذة عنك وخبراتك *" class="w-full rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">{{ old('bio') }}</textarea>
            </div>
            <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white rounded-full px-6 py-2.5 text-sm font-semibold transition-colors">إرسال الطلب</button>
        </form>
    </div>
</x-platform-layout>
