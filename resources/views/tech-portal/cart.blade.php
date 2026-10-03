<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <h2 class="font-markazi font-bold text-2xl text-maroon-800 leading-tight">سلة بوابة التقنية</h2>
        </div>
    </x-slot>

    <div class="bg-cream">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
            <a href="{{ route('tech-portal.index') }}" class="text-sm text-maroon-600 hover:text-maroon-800 transition-colors mb-6 inline-block">← رجوع لبوابة التقنية</a>

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

            @if ($services->isEmpty())
                <div class="bg-white border border-maroon-100 rounded-2xl p-12 text-center text-gray-500">
                    سلتك فارغة. <a href="{{ route('tech-portal.index') }}" class="text-maroon-700 hover:underline font-medium">تصفّح الخدمات</a>.
                </div>
            @else
                <div class="bg-white border border-maroon-100 rounded-2xl shadow-sm overflow-hidden mb-8">
                    <ul class="divide-y divide-maroon-50">
                        @foreach ($services as $service)
                            <li class="flex items-center justify-between px-5 py-4">
                                <div>
                                    <p class="font-semibold text-gray-900">{{ $service->title }}</p>
                                    @if ($service->price_note)
                                        <p class="text-xs text-gold-700">{{ $service->price_note }}</p>
                                    @endif
                                </div>
                                <form method="POST" action="{{ route('tech-portal.cart.remove', $service) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm text-red-600 hover:text-red-700 font-medium">إزالة</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="bg-white border border-maroon-100 rounded-2xl p-8">
                    <h3 class="font-markazi font-semibold text-xl text-maroon-800 mb-1">بيانات التواصل</h3>
                    <p class="text-sm text-gray-500 mb-6">سيتواصل معك فريقنا التقني لتفاصيل التنفيذ والتسعير.</p>

                    <form method="POST" action="{{ route('tech-portal.cart.submit') }}" class="space-y-4">
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
                            <label for="message" class="block text-sm font-semibold text-gray-700 mb-1.5">تفاصيل إضافية (اختياري)</label>
                            <textarea name="message" id="message" rows="4" maxlength="1000" class="w-full rounded-xl border-gray-200 focus:border-maroon-400 focus:ring-maroon-400" placeholder="عرّف باحتياجك باختصار...">{{ old('message') }}</textarea>
                        </div>
                        <button type="submit" class="bg-maroon-600 hover:bg-maroon-700 text-white rounded-full px-6 py-2.5 text-sm font-semibold transition-colors">إرسال الطلب</button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
