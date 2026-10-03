{{-- بوابة العميل — دعوة/إلغاء/استعادة وصول المدين لقضيته (المرحلة 2) --}}
<div x-show="tab === 'client'" x-cloak class="bg-white border border-gray-100 rounded-2xl p-7">
    @if (! $case->client_user_id)
        <h2 class="font-bold text-gray-900 mb-1">دعوة العميل</h2>
        <p class="text-xs text-gray-400 mb-5">يُنشأ حساب حقيقي بالمنصة للعميل — يصله بريد لتفعيله وتعيين كلمة مرور. الوصول محصور بملخّص القضية والمستندات فقط.</p>
        <form action="{{ route('bankruptcy-tech.cases.client.store', $case) }}" method="POST" class="grid sm:grid-cols-2 gap-4">
            @csrf
            <input type="text" name="name" placeholder="اسم العميل" required class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500" value="{{ old('name') }}">
            <input type="email" name="email" placeholder="البريد الإلكتروني" required class="rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500" value="{{ old('email') }}">
            <button type="submit" class="sm:col-span-2 bg-brand-600 hover:bg-brand-700 text-white rounded-full py-2.5 text-sm font-semibold transition-colors">إرسال دعوة</button>
        </form>
    @else
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h2 class="font-bold text-gray-900">{{ $case->client->name }}</h2>
                <p class="text-sm text-gray-500 mt-0.5">{{ $case->client->email }}</p>
                @if ($case->client_access_revoked_at)
                    <span class="inline-block mt-2 text-[11px] font-semibold px-2.5 py-1 rounded-full bg-red-50 text-red-700">الوصول مُلغى</span>
                @else
                    <span class="inline-block mt-2 text-[11px] font-semibold px-2.5 py-1 rounded-full bg-brand-50 text-brand-700">وصول فعّال</span>
                @endif
            </div>

            @if ($case->client_access_revoked_at)
                <form action="{{ route('bankruptcy-tech.cases.client.restore', $case) }}" method="POST">
                    @csrf
                    <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white rounded-full px-5 py-2 text-sm font-semibold transition-colors">استعادة الوصول</button>
                </form>
            @else
                <form action="{{ route('bankruptcy-tech.cases.client.revoke', $case) }}" method="POST" onsubmit="return confirm('تأكيد إلغاء وصول العميل؟')">
                    @csrf
                    <button type="submit" class="bg-white border border-red-200 text-red-700 hover:bg-red-50 rounded-full px-5 py-2 text-sm font-semibold transition-colors">إلغاء الوصول</button>
                </form>
            @endif
        </div>
    @endif
</div>
