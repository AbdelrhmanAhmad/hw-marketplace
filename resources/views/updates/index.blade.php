<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <span class="h-10 w-10 rounded-xl bg-gold-50 text-gold-600 flex items-center justify-center ring-1 ring-inset ring-gold-100">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 110-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 01-1.44-4.282m3.102.069a18.03 18.03 0 01-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 018.835 2.535M10.34 6.66a23.847 23.847 0 008.835-2.535m0 0A23.74 23.74 0 0018.795 3m.38 1.125a23.91 23.91 0 011.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 001.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 010 3.46" />
                </svg>
            </span>
            <div>
                <h2 class="font-bold text-xl text-gray-900 leading-tight">آخر التحديثات التشريعية</h2>
                <p class="text-sm text-gray-500">تعديلات ومشاريع الأنظمة الصادرة حديثًا</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if ($updates->isEmpty())
            <div class="bg-white border border-gray-100 rounded-2xl p-12 text-center text-gray-500">
                لا توجد تحديثات منشورة بعد.
            </div>
        @else
            <div class="relative space-y-5 mb-8">
                @foreach ($updates as $update)
                    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-6 hover:shadow-md transition-shadow">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span>
                            <p class="text-xs text-gray-400">{{ $update->published_at->translatedFormat('d F Y') }}</p>
                        </div>
                        <h3 class="font-bold text-gray-900 mb-2">{{ $update->title }}</h3>
                        <p class="text-sm text-gray-600 leading-relaxed mb-3">{{ $update->body }}</p>
                        @if ($update->lawEntry)
                            <a href="{{ route('laws.show', $update->lawEntry) }}" class="inline-flex items-center gap-1 text-sm text-brand-700 hover:underline font-medium">
                                عرض النظام المرتبط: {{ $update->lawEntry->title }} ←
                            </a>
                        @endif
                    </div>
                @endforeach
            </div>

            {{ $updates->links() }}
        @endif
    </div>
</x-app-layout>
