<x-platform-layout>
    <div class="bg-gradient-to-l from-maroon-800 via-maroon-700 to-gold-700 text-white">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12 flex items-center justify-between flex-wrap gap-4">
            <div>
                <h1 class="font-markazi font-bold text-3xl mb-1">إعلاناتي بمجتمع الخدمات</h1>
                <p class="text-cream/80 text-sm">اعرض خدماتك القانونية أو المالية ليراها العامة</p>
            </div>
            <a href="{{ route('community.dashboard.create') }}" class="bg-white text-maroon-700 hover:bg-cream rounded-full px-6 py-2.5 text-sm font-semibold shadow-sm transition-colors">
                + إعلان جديد
            </a>
        </div>
    </div>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        @if (session('status'))
            <div class="mb-8 bg-gold-50 border border-gold-200 text-gold-800 rounded-2xl px-5 py-4 text-sm font-medium">{{ session('status') }}</div>
        @endif

        @if ($listings->isEmpty())
            <div class="bg-white border border-maroon-100 rounded-2xl p-12 text-center text-gray-500">
                ما نشرت أي إعلان بعد. <a href="{{ route('community.dashboard.create') }}" class="text-maroon-700 hover:underline font-medium">ابدأ الآن</a>.
            </div>
        @else
            <div class="bg-white border border-maroon-100 rounded-2xl shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-maroon-50/60 text-maroon-700 text-xs">
                        <tr>
                            <th class="px-5 py-3 text-start font-semibold">العنوان</th>
                            <th class="px-5 py-3 text-start font-semibold">الحالة</th>
                            <th class="px-5 py-3 text-start font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-maroon-50">
                        @foreach ($listings as $listing)
                            <tr class="hover:bg-maroon-50/30 transition-colors">
                                <td class="px-5 py-4 text-gray-900">{{ $listing->title }}</td>
                                <td class="px-5 py-4">
                                    @if ($listing->isOpen())
                                        <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-green-50 text-green-700">مفتوح للعامة</span>
                                    @else
                                        <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-gray-100 text-gray-600">مُغلَق</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4">
                                    <a href="{{ route('community.dashboard.show', $listing) }}" class="text-maroon-700 hover:underline font-medium">إدارة</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-6">{{ $listings->links() }}</div>
        @endif
    </div>
</x-platform-layout>
