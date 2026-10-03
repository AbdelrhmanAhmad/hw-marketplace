<footer class="mp-shell-footer" role="contentinfo">
    <div class="mp-shell-footer__inner">
        <div class="mp-shell-footer__brand">
            <a href="{{ route('platform.home') }}">
                <img src="{{ asset('images/brand/logo0a.png') }}" alt="حكم ورقم" width="120" height="36">
            </a>
            <p class="mp-shell-footer__about">
                سوق تطبيقات حكم ورقم — فعّل أدوات مكتبك القانونية والمالية من مكان واحد، على سطح مستقل عن الموقع العام.
            </p>
        </div>

        <div>
            <h3>أقسام السوق</h3>
            <ul>
                <li><a href="{{ route('platform.home') }}">الرئيسية</a></li>
                <li><a href="{{ route('platform.marketplace') }}">متجر التطبيقات</a></li>
                <li><a href="{{ route('marefa.home') }}">بوابة معرفة</a></li>
            </ul>
        </div>

        <div>
            <h3>تواصل</h3>
            <ul>
                <li><a href="mailto:hello@hw.sa">hello@hw.sa</a></li>
                <li><span class="text-white/60 text-sm">الرياض، المملكة العربية السعودية</span></li>
            </ul>
        </div>
    </div>

    <div class="mp-shell-footer__bar">
        © {{ now()->year }} حكم ورقم — سوق التطبيقات. جميع الحقوق محفوظة.
    </div>
</footer>
