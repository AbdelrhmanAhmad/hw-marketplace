@if ($errors->any())
    <div class="mb-6 bg-red-50 border border-red-100 text-red-700 rounded-2xl px-5 py-4 text-sm font-medium">
        @foreach ($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </div>
@endif

@if (isset($article) && $article->status === 'rejected' && $article->rejection_reason)
    <div class="mb-6 bg-red-50 border border-red-100 text-red-700 rounded-2xl px-5 py-4 text-sm">
        <p class="font-semibold mb-1">رُفض هذا المقال — السبب:</p>
        <p>{{ $article->rejection_reason }}</p>
    </div>
@endif

<form action="{{ isset($article) ? route('articles.dashboard.update', $article) : route('articles.dashboard.store') }}" method="POST" enctype="multipart/form-data" class="bg-white border border-gray-100 rounded-2xl p-7 space-y-4">
    @csrf
    @if (isset($article))
        @method('PATCH')
    @endif

    <input type="text" name="title" placeholder="عنوان المقال *" value="{{ old('title', $article->title ?? '') }}" class="w-full rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500 text-lg font-bold">

    <select name="article_category_id" class="w-full rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">
        <option value="">بلا تصنيف</option>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected(old('article_category_id', $article->article_category_id ?? null) == $category->id)>{{ $category->name }}</option>
        @endforeach
    </select>

    <textarea name="excerpt" rows="2" placeholder="مقتطف قصير يظهر بقائمة المقالات (اختياري)" class="w-full rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500">{{ old('excerpt', $article->excerpt ?? '') }}</textarea>

    <div>
        <input type="file" name="cover_image" accept="image/*" class="w-full rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500 text-sm">
        <p class="text-xs text-gray-400 mt-1">صورة غلاف اختيارية.</p>
        @if (isset($article) && $article->coverImageUrl())
            <img src="{{ $article->coverImageUrl() }}" alt="" class="h-20 rounded-lg mt-2">
        @endif
    </div>

    <textarea name="body" rows="16" placeholder="محتوى المقال *" class="w-full rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500 leading-relaxed">{{ old('body', $article->body ?? '') }}</textarea>

    <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white rounded-full px-6 py-2.5 text-sm font-semibold transition-colors">حفظ</button>
</form>

@if (isset($article) && in_array($article->status, ['draft', 'rejected'], true))
    <form action="{{ route('articles.dashboard.submit', $article) }}" method="POST" class="mt-3">
        @csrf
        <button type="submit" class="bg-white border border-brand-200 text-brand-700 hover:bg-brand-50 rounded-full px-6 py-2.5 text-sm font-semibold transition-colors">
            إرسال للمراجعة
        </button>
    </form>
@endif
