<x-platform-layout>
    <div class="bg-gradient-to-l from-forest to-brand-700 text-white">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-14 flex items-center justify-between flex-wrap gap-4">
            <div>
                <h1 class="text-2xl font-bold mb-1">إفلاس تك</h1>
                <p class="text-brand-50 text-sm">
                    قضاياك — {{ $activeOrganization?->name ?? 'مساحتك الشخصية' }}
                </p>
            </div>
            <a href="{{ route('bankruptcy-tech.cases.create') }}" class="bg-white text-brand-700 hover:bg-brand-50 rounded-full px-6 py-2.5 text-sm font-semibold shadow-sm transition-colors">
                + قضية جديدة
            </a>
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        @if (session('status'))
            <div class="mb-8 bg-brand-50 border border-brand-100 text-brand-700 rounded-2xl px-5 py-4 text-sm font-medium">
                {{ session('status') }}
            </div>
        @endif

        @if ($cases->isEmpty())
            <div class="bg-white border border-gray-100 rounded-2xl p-14 text-center text-gray-500">
                <p class="mb-3">ما عندك أي قضية بعد.</p>
                <a href="{{ route('bankruptcy-tech.cases.create') }}" class="text-brand-700 font-medium hover:underline">أنشئ أول قضية</a>
            </div>
        @else
            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-xs">
                            <tr>
                                <th class="px-5 py-3 text-start font-semibold">رقم القضية</th>
                                <th class="px-5 py-3 text-start font-semibold">العنوان</th>
                                <th class="px-5 py-3 text-start font-semibold">الحالة</th>
                                <th class="px-5 py-3 text-start font-semibold">الأطراف</th>
                                <th class="px-5 py-3 text-start font-semibold">الإجراءات</th>
                                <th class="px-5 py-3 text-start font-semibold">فُتحت</th>
                                <th class="px-5 py-3 text-start font-semibold"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($cases as $case)
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="px-5 py-4">
                                        <a href="{{ route('bankruptcy-tech.cases.show', $case) }}" class="font-semibold text-brand-700 hover:underline">
                                            {{ $case->case_number }}
                                        </a>
                                    </td>
                                    <td class="px-5 py-4 text-gray-900">{{ $case->title }}</td>
                                    <td class="px-5 py-4">
                                        <x-bankruptcy-tech.case-status-badge :status="$case->status" />
                                    </td>
                                    <td class="px-5 py-4 text-gray-500">{{ $case->parties_count }}</td>
                                    <td class="px-5 py-4 text-gray-500">{{ $case->procedures_count }}</td>
                                    <td class="px-5 py-4 text-gray-400 text-xs">{{ $case->opened_at?->translatedFormat('d F Y') }}</td>
                                    <td class="px-5 py-4 text-left">
                                        @can('manage', $case)
                                            <form action="{{ route('bankruptcy-tech.cases.destroy', $case) }}" method="POST" onsubmit="return confirm('حذف القضية «{{ $case->title }}» نهائيًا؟ لا يمكن التراجع عن هذا الإجراء — كل الدائنين/الأصول/المستندات المرتبطة بها ستُحذَف أيضًا.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-gray-400 hover:text-red-600 text-xs font-medium transition-colors">حذف</button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6">{{ $cases->links() }}</div>
        @endif
    </div>
</x-platform-layout>
