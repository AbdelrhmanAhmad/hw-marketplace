<footer class="bg-forest text-white mt-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
        <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <x-brand-logo dark icon-size="h-9 w-9" />
                <p class="text-sm text-white/60 mt-4 leading-relaxed">
                    منصة سعودية تجمع بين الخدمات القانونية والمالية والمحاسبية في مكان واحد، لتبسيط الوصول للخدمات وجعل التعاقد والدفع والتمويل أسرع وأسهل.
                </p>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-gold-400 mb-4">بوابة معرفة</h3>
                <ul class="space-y-2.5 text-sm text-white/70">
                    <li><a href="{{ route('marefa.home') }}" class="hover:text-white transition-colors">الرئيسية</a></li>
                    <li><a href="{{ route('laws.index') }}" class="hover:text-white transition-colors">فهرس الأنظمة</a></li>
                    <li><a href="{{ route('updates.index') }}" class="hover:text-white transition-colors">آخر التحديثات</a></li>
                    <li><a href="{{ route('calculators.gratuity') }}" class="hover:text-white transition-colors">الحاسبات القانونية</a></li>
                </ul>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-gold-400 mb-4">أقسام المنصة</h3>
                <ul class="space-y-2.5 text-sm text-white/70">
                    <li>
                        <a href="{{ route('platform.marketplace') }}" class="hover:text-white transition-colors">متجر التطبيقات</a>
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="text-white/40">الخدمات المالية</span>
                        <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-white/10 text-white/60">قريبًا</span>
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="text-white/40">الخدمات المحاسبية</span>
                        <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-white/10 text-white/60">قريبًا</span>
                    </li>
                </ul>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-gold-400 mb-4">تواصل معنا</h3>
                <ul class="space-y-2.5 text-sm text-white/70">
                    <li>hello@hw.sa</li>
                    <li>الرياض، المملكة العربية السعودية</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="border-t border-white/10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 text-xs text-white/50 flex flex-col sm:flex-row justify-between gap-2">
            <p>© {{ now()->year }} حكم ورقم — بوابة معرفة. جميع الحقوق محفوظة.</p>
            <p>محتوى هذه النسخة التجريبية عبارة عن بيانات عيّنة توضيحية وليست نسخة رسمية معتمدة من الجهات الحكومية.</p>
        </div>
    </div>
</footer>
