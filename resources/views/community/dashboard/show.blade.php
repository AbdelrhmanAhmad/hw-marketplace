<x-platform-layout>
    <div class="bg-cream">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            @if (session('status'))
                <div class="mb-6 bg-gold-50 border border-gold-200 text-gold-800 rounded-2xl px-5 py-4 text-sm font-medium">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-6 bg-red-50 border border-red-100 text-red-700 rounded-2xl px-5 py-4 text-sm">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="bg-white border border-maroon-100 rounded-2xl p-8 mb-8">
                <div class="flex items-center gap-2 mb-4">
                    <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-maroon-50 text-maroon-700 ring-1 ring-inset ring-maroon-100">{{ $listing->category }}</span>
                    @if ($listing->isOpen())
                        <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-green-50 text-green-700">مفتوح للعامة</span>
                        <a href="{{ route('community.show', $listing) }}" class="text-[11px] text-gold-700 hover:underline">عرض كما يراه الزائر ←</a>
                    @else
                        <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-gray-100 text-gray-500">مُغلَق</span>
                    @endif
                </div>

                <h1 class="font-markazi font-bold text-2xl text-maroon-800 mb-2">{{ $listing->title }}</h1>
                <p class="text-gray-700 whitespace-pre-line leading-relaxed mb-6">{{ $listing->description }}</p>

                @if ($listing->isOpen())
                    <form method="POST" action="{{ route('community.dashboard.close', $listing) }}">
                        @csrf
                        <button type="submit" class="text-sm text-red-600 hover:text-red-700 font-medium" onclick="return confirm('إيقاف ظهور هذا الإعلان للعامة؟')">إيقاف ظهور الإعلان</button>
                    </form>
                @endif
            </div>

            <div class="bg-white border border-maroon-100 rounded-2xl p-8">
                <h2 class="font-markazi font-semibold text-xl text-maroon-800 mb-4">الاستفسارات ({{ $inquiries->count() }})</h2>
                @if ($inquiries->isEmpty())
                    <p class="text-sm text-gray-500">ما وصل استفسار بعد.</p>
                @else
                    <ul class="space-y-4">
                        @foreach ($inquiries as $inquiry)
                            <li class="border border-maroon-100/70 rounded-xl p-4">
                                <p class="text-sm font-semibold text-gray-900">{{ $inquiry->name }}</p>
                                <p class="text-xs text-gray-400 mb-2">{{ $inquiry->email }}{{ $inquiry->phone ? ' · '.$inquiry->phone : '' }} · {{ $inquiry->created_at->diffForHumans() }}</p>
                                @if ($inquiry->message)
                                    <p class="text-sm text-gray-600 whitespace-pre-line">{{ $inquiry->message }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="mt-6">
                <a href="{{ route('community.dashboard.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&rarr; رجوع لإعلاناتي</a>
            </div>
        </div>
    </div>
</x-platform-layout>
