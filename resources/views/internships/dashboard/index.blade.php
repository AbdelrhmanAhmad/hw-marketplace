<x-platform-layout>
    <div class="bg-gradient-to-l from-forest to-brand-700 text-white">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12 flex items-center justify-between flex-wrap gap-4">
            <div>
                <h1 class="text-2xl font-bold mb-1">فرصي بالتدريب التعاوني</h1>
                <p class="text-brand-50 text-sm">انشر فرص تدريب للطلاب لدى مكتبك أو شركتك</p>
            </div>
            <a href="{{ route('internships.dashboard.create') }}" class="bg-white text-brand-700 hover:bg-brand-50 rounded-full px-6 py-2.5 text-sm font-semibold shadow-sm transition-colors">
                + فرصة جديدة
            </a>
        </div>
    </div>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        @if (session('status'))
            <div class="mb-8 bg-brand-50 border border-brand-100 text-brand-700 rounded-2xl px-5 py-4 text-sm font-medium">{{ session('status') }}</div>
        @endif

        @if ($opportunities->isEmpty())
            <div class="bg-white border border-gray-100 rounded-2xl p-12 text-center text-gray-500">
                ما نشرت أي فرصة بعد. <a href="{{ route('internships.dashboard.create') }}" class="text-brand-700 hover:underline font-medium">ابدأ الآن</a>.
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
                        @foreach ($opportunities as $opportunity)
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-5 py-4 text-gray-900">{{ $opportunity->title }}</td>
                                <td class="px-5 py-4">
                                    @if ($opportunity->isOpen())
                                        <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-green-50 text-green-700">مفتوحة</span>
                                    @else
                                        <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-gray-100 text-gray-600">مُغلَقة</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4">
                                    <a href="{{ route('internships.dashboard.show', $opportunity) }}" class="text-brand-700 hover:underline font-medium">إدارة</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-6">{{ $opportunities->links() }}</div>
        @endif
    </div>
</x-platform-layout>
