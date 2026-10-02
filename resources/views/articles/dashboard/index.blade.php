<x-platform-layout>
    <div class="bg-gradient-to-l from-forest to-brand-700 text-white">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12 flex items-center justify-between flex-wrap gap-4">
            <div>
                <h1 class="text-2xl font-bold mb-1">بوابة المقالات</h1>
                <p class="text-brand-50 text-sm">لوحة المؤلف</p>
            </div>
            @if ($author?->isApproved())
                <a href="{{ route('articles.dashboard.create') }}" class="bg-white text-brand-700 hover:bg-brand-50 rounded-full px-6 py-2.5 text-sm font-semibold shadow-sm transition-colors">
                    + مقال جديد
                </a>
            @endif
        </div>
    </div>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        @if (session('status'))
            <div class="mb-8 bg-brand-50 border border-brand-100 text-brand-700 rounded-2xl px-5 py-4 text-sm font-medium">{{ session('status') }}</div>
        @endif

        @if (! $author)
            <div class="bg-white border border-gray-100 rounded-2xl p-10 text-center">
                <h2 class="font-bold text-gray-900 mb-2">صِر مؤلفًا بالبوابة</h2>
                <p class="text-gray-500 text-sm mb-6">قدّم طلب تأليف — بعد موافقة فريق المنصة تقدر تنشر مقالاتك القانونية والمالية.</p>
                <a href="{{ route('articles.dashboard.author.create') }}" class="inline-block bg-brand-600 hover:bg-brand-700 text-white rounded-full px-6 py-2.5 text-sm font-semibold transition-colors">
                    طلب أن تصبح مؤلفًا
                </a>
            </div>
        @elseif ($author->status === 'pending')
            <div class="bg-gold-50 border border-gold-100 rounded-2xl p-8 text-center">
                <p class="font-semibold text-gold-800 mb-1">طلبك بانتظار المراجعة</p>
                <p class="text-sm text-gold-700">فريق المنصة يراجع طلب التأليف — بنعلمك فور الموافقة.</p>
            </div>
        @elseif ($author->status === 'rejected')
            <div class="bg-red-50 border border-red-100 rounded-2xl p-8 text-center">
                <p class="font-semibold text-red-800 mb-1">لم تتم الموافقة على طلبك</p>
                @if ($author->rejection_reason)
                    <p class="text-sm text-red-700 mb-4">السبب: {{ $author->rejection_reason }}</p>
                @endif
                <a href="{{ route('articles.dashboard.author.create') }}" class="inline-block bg-white border border-red-200 text-red-700 hover:bg-red-50 rounded-full px-6 py-2 text-sm font-semibold transition-colors">
                    إعادة التقديم
                </a>
            </div>
        @else
            <h2 class="font-bold text-gray-900 mb-5">مقالاتي</h2>
            @if ($articles->isEmpty())
                <div class="bg-white border border-gray-100 rounded-2xl p-12 text-center text-gray-500">
                    ما كتبت أي مقال بعد. <a href="{{ route('articles.dashboard.create') }}" class="text-brand-700 hover:underline font-medium">ابدأ الآن</a>.
                </div>
            @else
                <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-xs">
                            <tr>
                                <th class="px-5 py-3 text-start font-semibold">العنوان</th>
                                <th class="px-5 py-3 text-start font-semibold">الحالة</th>
                                <th class="px-5 py-3 text-start font-semibold"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($articles as $article)
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="px-5 py-4 text-gray-900">{{ $article->title }}</td>
                                    <td class="px-5 py-4">
                                        @php
                                            $labels = ['draft' => 'مسودة', 'pending_review' => 'بانتظار المراجعة', 'published' => 'منشور', 'rejected' => 'مرفوض'];
                                            $colors = ['draft' => 'bg-gray-100 text-gray-600', 'pending_review' => 'bg-gold-50 text-gold-700', 'published' => 'bg-green-50 text-green-700', 'rejected' => 'bg-red-50 text-red-700'];
                                        @endphp
                                        <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full {{ $colors[$article->status] }}">{{ $labels[$article->status] }}</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <a href="{{ route('articles.dashboard.edit', $article) }}" class="text-brand-700 hover:underline font-medium">تعديل</a>
                                        @if ($article->isPublished())
                                            <a href="{{ route('articles.show', $article) }}" class="text-gray-400 hover:text-gray-700 ms-3">عرض</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-6">{{ $articles->links() }}</div>
            @endif
        @endif
    </div>
</x-platform-layout>
