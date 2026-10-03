{{--
  Glassmorphism home (default). Revert: MARKETPLACE_HOME_VARIANT=classic
--}}
<x-platform-layout body-class="font-sans antialiased mp-glass-body">
    <div class="mp-glass-page">
        <section class="mp-glass-hero" aria-label="مقدمة سوق تطبيقات حكم ورقم">
            <div class="mp-glass-panel">
                <p class="mp-glass-eyebrow">سوق التطبيقات المهنية</p>
                <h1 class="mp-glass-brand">حكم ورقم</h1>
                <p class="mp-glass-headline">منصة تشغيل للتطبيقات القانونية والمالية داخل حساب واحد</p>
                <p class="mp-glass-lead">
                    فعّل تطبيقات مكتبك من متجر واحد، وأدر الوصول بوضوح — بهوية حكم ورقم على subdomain مستقل.
                </p>

                <div class="mp-glass-actions">
                    <a href="{{ route('platform.marketplace') }}" class="mp-glass-btn mp-glass-btn--primary">
                        ادخل متجر التطبيقات
                    </a>
                    <a href="#layers" class="mp-glass-btn mp-glass-btn--ghost">
                        تعرّف على المنصة
                    </a>
                </div>
            </div>

            <aside class="mp-glass-visual" aria-label="معاينة متجر التطبيقات">
                <div
                    class="mp-glass-visual__media"
                    style="background-image: linear-gradient(160deg, rgba(7,44,27,0.88) 0%, rgba(0,121,58,0.62) 55%, rgba(7,44,27,0.45) 100%), url('{{ asset('images/patterns/hero-bg-wide.webp') }}');"
                ></div>

                <div class="mp-glass-visual__content">
                    <div class="mp-glass-visual__top">
                        <p class="mp-glass-visual__kicker">متجر التطبيقات</p>
                        <p class="mp-glass-visual__count">6 تطبيقات جاهزة للتفعيل</p>
                    </div>

                    <ul class="mp-glass-apps">
                        <li class="mp-glass-app">
                            <span class="mp-glass-app__mark">مع</span>
                            <span>
                                <strong>معرفة</strong>
                                <small>محتوى قانوني منظّم</small>
                            </span>
                        </li>
                        <li class="mp-glass-app">
                            <span class="mp-glass-app__mark">إف</span>
                            <span>
                                <strong>إفلاس تك</strong>
                                <small>إدارة قضايا الإفلاس</small>
                            </span>
                        </li>
                        <li class="mp-glass-app">
                            <span class="mp-glass-app__mark">عق</span>
                            <span>
                                <strong>عقود</strong>
                                <small>صياغة ومتابعة</small>
                            </span>
                        </li>
                        <li class="mp-glass-app">
                            <span class="mp-glass-app__mark">ام</span>
                            <span>
                                <strong>امتثال</strong>
                                <small>رقابة ومتطلبات</small>
                            </span>
                        </li>
                        <li class="mp-glass-app">
                            <span class="mp-glass-app__mark">اس</span>
                            <span>
                                <strong>استشارات</strong>
                                <small>طلبات ومتابعة</small>
                            </span>
                        </li>
                        <li class="mp-glass-app">
                            <span class="mp-glass-app__mark">نظ</span>
                            <span>
                                <strong>أنظمة</strong>
                                <small>مرجع تشريعي</small>
                            </span>
                        </li>
                    </ul>
                </div>
            </aside>
        </section>

        <section id="layers" class="mp-glass-section scroll-mt-20">
            <div class="mp-glass-section__head">
                <h2>كيف تُبنى حكم ورقم</h2>
                <p>خمس طبقات فوق حساب موحّد — والمتجر بوابة التطبيقات هنا.</p>
            </div>

            <div class="mp-glass-grid">
                <div class="mp-glass-card">
                    <span class="mp-glass-icon">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.429 9.75L2.25 12l4.179 2.25m0-4.5l5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L21.75 12l-4.179 2.25m0 0l4.179 2.25L12 21.75 2.25 16.5l4.179-2.25m11.142 0l-5.571 3-5.571-3" />
                        </svg>
                    </span>
                    <h3>Core Platform</h3>
                    <p>حسابك ومكتبك وصلاحياتك</p>
                </div>

                <a href="{{ route('platform.marketplace') }}" class="mp-glass-card mp-glass-card--active" aria-label="ادخل متجر التطبيقات من هنا">
                    <span class="mp-glass-cue" aria-hidden="true">
                        <span class="mp-glass-cue__triangle"></span>
                        <span class="mp-glass-cue__badge">اضغط هنا</span>
                    </span>
                    <span class="mp-glass-icon">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 1.98-4.684 2.582-7.128a.75.75 0 00-.75-.906H5.106M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                        </svg>
                    </span>
                    <h3>Marketplace</h3>
                    <p>توزيع التطبيقات والخدمات</p>
                </a>

                <div class="mp-glass-card">
                    <span class="mp-glass-icon">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                        </svg>
                    </span>
                    <h3>Applications</h3>
                    <p>تطبيقات متخصصة فوق المنصة</p>
                </div>

                <div class="mp-glass-card mp-glass-card--muted">
                    <span class="mp-glass-icon">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                        </svg>
                    </span>
                    <h3>Integrations</h3>
                    <p>ربط بمزودين خارجيين</p>
                    <span class="mp-glass-pill">قريبًا</span>
                </div>

                <div class="mp-glass-card mp-glass-card--muted">
                    <span class="mp-glass-icon">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </span>
                    <h3>Partners</h3>
                    <p>شركاء يبنون تطبيقات</p>
                    <span class="mp-glass-pill">مستقبلي</span>
                </div>
            </div>
        </section>

        <section class="mp-glass-pillars" aria-label="قيم المنصة">
            <div class="mp-glass-pillars__inner">
                <article class="mp-glass-pillar">
                    <div class="mp-glass-pillar__head">
                        <span class="mp-glass-pillar__icon" aria-hidden="true">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </span>
                        <span class="mp-glass-pillar__index" aria-hidden="true">01</span>
                    </div>
                    <h3>دقة موثوقة</h3>
                    <p>محتوى منظّم وفق مصادر رسمية — وضوح يبني ثقة المكتب والعميل.</p>
                </article>

                <article class="mp-glass-pillar">
                    <div class="mp-glass-pillar__head">
                        <span class="mp-glass-pillar__icon" aria-hidden="true">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </span>
                        <span class="mp-glass-pillar__index" aria-hidden="true">02</span>
                    </div>
                    <h3>سرعة في الإجراء</h3>
                    <p>تطبيقات جاهزة تقلّل التكرار وتختصر مسار العمل اليومي.</p>
                </article>

                <article class="mp-glass-pillar">
                    <div class="mp-glass-pillar__head">
                        <span class="mp-glass-pillar__icon" aria-hidden="true">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.75c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.75h-.152c-3.196 0-6.1-1.248-8.25-3.286z" />
                            </svg>
                        </span>
                        <span class="mp-glass-pillar__index" aria-hidden="true">03</span>
                    </div>
                    <h3>أمان واحترافية</h3>
                    <p>تجربة مصمّمة للمحامين والشركات — وصول واضح وحدود واضحة.</p>
                </article>
            </div>
        </section>
    </div>
</x-platform-layout>
