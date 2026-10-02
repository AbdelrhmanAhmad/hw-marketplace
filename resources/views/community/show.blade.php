<x-app-layout>
    <div class="bg-cream">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
            <a href="{{ route('community.index') }}" class="text-sm text-maroon-600 hover:text-maroon-800 transition-colors mb-6 inline-block">← رجوع لمجتمع الخدمات</a>

            @if (session('status'))
                <div class="mb-6 bg-white border border-gold-200 text-gold-800 rounded-2xl px-5 py-4 text-sm font-medium">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-6 bg-red-50 border border-red-100 text-red-700 rounded-2xl px-5 py-4 text-sm">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <span class="text-xs bg-maroon-50 text-maroon-700 px-2.5 py-1 rounded-full ring-1 ring-inset ring-maroon-100">{{ $listing->category }}</span>
            <h1 class="font-markazi font-bold text-3xl sm:text-4xl text-maroon-800 mt-4 mb-4">{{ $listing->title }}</h1>

            <div class="flex items-center gap-2 text-sm text-gray-500 mb-8 pb-8 border-b border-gold-100">
                <span class="font-medium text-maroon-700">{{ $listing->user->name }}</span>
                <span>· {{ $listing->created_at->translatedFormat('d F Y') }}</span>
            </div>

            <div class="text-gray-700 leading-loose text-[15px] whitespace-pre-line mb-10">{{ $listing->description }}</div>

            @if ($isOwner)
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
                    <a href="{{ route('community.dashboard.index') }}" class="inline-block mt-6 text-sm text-gold-700 hover:text-gold-600 font-semibold">إدارة إعلاناتي ←</a>
                </div>
            @else
                <div class="bg-white border border-maroon-100 rounded-2xl p-8">
                    <h2 class="font-markazi font-semibold text-xl text-maroon-800 mb-1">تواصل مع صاحب الإعلان</h2>
                    <p class="text-sm text-gray-500 mb-6">
                        @switch($listing->contact_method)
                            @case('phone') الهاتف: <span class="font-medium text-maroon-700" dir="ltr">{{ $listing->contact_value }}</span> @break
                            @case('whatsapp') واتساب: <span class="font-medium text-maroon-700" dir="ltr">{{ $listing->contact_value }}</span> @break
                            @default البريد الإلكتروني: <span class="font-medium text-maroon-700">{{ $listing->contact_value }}</span>
                        @endswitch
                        — أو أرسل استفسارك مباشرة من النموذج بالأسفل.
                    </p>

                    <form method="POST" action="{{ route('community.inquiry', $listing) }}" class="space-y-4">
                        @csrf
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label for="name" class="block text-sm font-semibold text-gray-700 mb-1.5">الاسم</label>
                                <input type="text" name="name" id="name" value="{{ old('name', auth()->user()?->name) }}" maxlength="150" required class="w-full rounded-xl border-gray-200 focus:border-maroon-400 focus:ring-maroon-400">
                            </div>
                            <div>
                                <label for="email" class="block text-sm font-semibold text-gray-700 mb-1.5">البريد الإلكتروني</label>
                                <input type="email" name="email" id="email" value="{{ old('email', auth()->user()?->email) }}" maxlength="255" required class="w-full rounded-xl border-gray-200 focus:border-maroon-400 focus:ring-maroon-400">
                            </div>
                        </div>
                        <div>
                            <label for="phone" class="block text-sm font-semibold text-gray-700 mb-1.5">الجوال (اختياري)</label>
                            <input type="text" name="phone" id="phone" value="{{ old('phone') }}" maxlength="30" class="w-full rounded-xl border-gray-200 focus:border-maroon-400 focus:ring-maroon-400">
                        </div>
                        <div>
                            <label for="message" class="block text-sm font-semibold text-gray-700 mb-1.5">رسالتك</label>
                            <textarea name="message" id="message" rows="4" maxlength="1000" class="w-full rounded-xl border-gray-200 focus:border-maroon-400 focus:ring-maroon-400" placeholder="عرّف بحاجتك باختصار...">{{ old('message') }}</textarea>
                        </div>
                        <button type="submit" class="bg-maroon-600 hover:bg-maroon-700 text-white rounded-full px-6 py-2.5 text-sm font-semibold transition-colors">إرسال الاستفسار</button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
