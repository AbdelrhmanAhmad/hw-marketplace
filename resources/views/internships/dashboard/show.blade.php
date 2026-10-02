<x-platform-layout>
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        @if (session('status'))
            <div class="mb-6 bg-brand-50 border border-brand-100 text-brand-700 rounded-2xl px-5 py-4 text-sm font-medium">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-6 bg-red-50 border border-red-100 text-red-700 rounded-2xl px-5 py-4 text-sm">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="bg-white border border-gray-100 rounded-2xl p-8 mb-8">
            <div class="flex items-center gap-2 mb-4">
                <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-gray-100 text-gray-600">{{ $opportunity->category }}</span>
                @if ($opportunity->isOpen())
                    <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-green-50 text-green-700">مفتوحة</span>
                    <a href="{{ route('internships.show', $opportunity) }}" class="text-[11px] text-brand-700 hover:underline">عرض كما يراها الطالب ←</a>
                @else
                    <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-gray-100 text-gray-500">مُغلَقة</span>
                @endif
            </div>

            <h1 class="text-xl font-bold text-gray-900 mb-2">{{ $opportunity->title }}</h1>
            <p class="text-gray-700 whitespace-pre-line leading-relaxed mb-6">{{ $opportunity->description }}</p>

            @if ($opportunity->isOpen())
                <form method="POST" action="{{ route('internships.dashboard.close', $opportunity) }}">
                    @csrf
                    <button type="submit" class="text-sm text-red-600 hover:text-red-700 font-medium" onclick="return confirm('إغلاق هذي الفرصة؟')">إغلاق الفرصة</button>
                </form>
            @endif
        </div>

        <div class="bg-white border border-gray-100 rounded-2xl p-8">
            <h2 class="font-bold text-gray-900 mb-4">المتقدّمون ({{ $applications->count() }})</h2>
            @if ($applications->isEmpty())
                <p class="text-sm text-gray-500">ما تقدّم أحد بعد.</p>
            @else
                <ul class="space-y-4">
                    @foreach ($applications as $application)
                        <li class="border border-gray-100 rounded-xl p-4">
                            <p class="text-sm font-semibold text-gray-900">{{ $application->user->name }}</p>
                            <p class="text-xs text-gray-400 mb-2">{{ $application->user->email }} · {{ $application->created_at->diffForHumans() }}</p>
                            @if ($application->message)
                                <p class="text-sm text-gray-600 whitespace-pre-line mb-3">{{ $application->message }}</p>
                            @endif
                            @if ($application->documents->isNotEmpty())
                                <div class="flex flex-wrap gap-2 mt-2">
                                    @foreach ($application->documents as $document)
                                        <a href="{{ route('internships.dashboard.applications.documents.download', [$opportunity, $application, $document]) }}"
                                            class="inline-flex items-center gap-1.5 text-xs bg-brand-50 text-brand-700 hover:bg-brand-100 px-3 py-1.5 rounded-full font-medium transition-colors">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                            </svg>
                                            {{ $document->label() }}
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="mt-6">
            <a href="{{ route('internships.dashboard.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&rarr; رجوع لفرصي</a>
        </div>
    </div>
</x-platform-layout>
