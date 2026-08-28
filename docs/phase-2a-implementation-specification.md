# Phase 2A — Active Organization Context — Implementation Specification

**الحالة:** Specification معتمدة للتنفيذ — 🟢 الكود مُصرَّح به بعدها مباشرة (بموافقتك الصريحة).
**النطاق الحصري:** `User → Organizations → Membership → Active Organization Context → Context-aware UI`. **لا شيء آخر.**
**المرجع:** `phase-2-organization-access-design.md` قسم B.1/C (التصميم الأصلي) + AD-010/AD-011.

**ممنوع صراحة بهذي المرحلة (يبقى 🔴 حتى مراجعة نتائج 2A):** Organization Subscription، `SubscriptionSeat`، Organization Access، Organization Authorization، أي Migration جديدة، أي تعديل على `Subscription`/`AccessAssignment`/`EntitlementResolver` الموجودين من Phase 1b.

---

# A. Current Code Inventory (الدلتا منذ Phase 1b)

| العنصر | الحالة الحقيقية اليوم |
|---|---|
| `Organization`, `Membership`, `MembershipRole` | موجودة من Core Platform Phase 1 — `User::organizations(): BelongsToMany`, `User::memberships(): HasMany` جاهزتان فعليًا |
| جلسة تحمل سياق مؤسسة | **غير موجودة إطلاقًا** |
| Middleware مخصَّص | **لا يوجد أي Middleware مخصَّص بالمشروع كاملًا** (مؤكَّد بجرد Phase 1a الأصلي، لا يزال صحيحًا) |
| هيدر `layouts/platform.blade.php` | ثابت — شعار + (لو مسجّل) رابط "لوحتي" وزر "الدخول للمنصة" فقط، **لا مبدّل سياق** |
| My Apps (`platform/my-apps.blade.php`) | تعرض اشتراكات شخصية فقط، لا إشارة لأي سياق مؤسسة |

---

# B. Domain Design — بلا قاعدة بيانات جديدة إطلاقًا

**لا Migration بهذي المرحلة.** السياق النشط جلسة بحتة — لا حاجة لتخزينه بقاعدة البيانات (لا يحتاج البقاء بعد انتهاء الجلسة، مطابق لتصميم B.1 الأصلي).

### الجلسة
مفتاح واحد: `session('active_organization_id')` — قيمته `null` ("شخصي") أو `int` (معرّف مؤسسة).

### `App\Support\ActiveOrganizationContext` (خدمة جديدة)
```
current(): ?Organization       — يقرأ الجلسة، يرجّع null أو نموذج Organization
switchTo(Organization $org): void   — يتحقق العضوية أولًا (يرمي استثناء لو ليس عضوًا)، يكتب الجلسة
switchToPersonal(): void       — يمسح المفتاح من الجلسة
```
**لا Constructor Injection معقَّد** — Facade-like استدعاء ثابت بسيط (نمط `PlatformApps` الحالي)، يقرأ `Auth::user()` داخليًا وقت الحاجة. هذا يبقيها قابلة للاستدعاء من أي مكان (Blade، Controller) بلا حقن صريح بكل نقطة.

### Middleware — `App\Http\Middleware\ValidateActiveOrganizationContext`
يعمل على كل طلب مصادَق (`auth` middleware group): يقرأ `session('active_organization_id')`، لو موجود يتحقق فعليًا إن `Auth::user()` لا يزال عضوًا بتلك المؤسسة (`Membership::where('user_id', ...)->where('organization_id', ...)->exists()`) — **لو لا، يمسح القيمة من الجلسة تلقائيًا ويعيد لـ"شخصي" بصمت** (لا خطأ 500، لا رسالة مزعجة، تراجع سلس). هذا يمنع سيناريو "جلسة قديمة تشير لمؤسسة أُزيل منها المستخدم" (Failure Scenario بقسم K من وثيقة التصميم).

---

# C. Routes

| المسار | Method | Auth | الغرض |
|---|---|---|---|
| `POST /organization-context/{organization}` | POST | نعم | تبديل لمؤسسة معيّنة — يتحقق العضوية بالـController أيضًا (دفاع مزدوج، لا اعتماد على Middleware وحده) |
| `POST /organization-context/personal` | POST | نعم | العودة لسياق "شخصي" |

**Controller:** `App\Http\Controllers\OrganizationContextController` (جديد، بسيط، تابعان فقط).

---

# D. UI Mapping

### مبدّل الهيدر (`layouts/platform.blade.php`، `@auth` فقط)
Dropdown بسيط (نمط Alpine.js، مطابق للنمط الموجود فعليًا بـ`x-interest-modal`): يعرض "شخصي" + قائمة مؤسسات المستخدم (`Auth::user()->organizations`). العنصر النشط حاليًا مُعلَّم بوضوح. لا مؤسسات؟ لا يظهر المبدّل إطلاقًا (لا فائدة له بلا خيارات فعلية — قاعدة "لا قسم بلا محتوى حقيقي" المعتمدة بكل الوثائق السابقة).

### شريط سياق على My Apps (`platform/my-apps.blade.php`)
سطر واحد أعلى الصفحة: "أنت الآن تعمل باسم: [شخصي / اسم المؤسسة]" — **معلوماتي بحت**، لا تأثير على المحتوى المعروض تحته بهذي المرحلة (My Apps تبقى تعرض الاشتراكات الشخصية فقط — Organization Subscription نفسها 🔴 غير موجودة بعد، Phase 2B). هذا يُثبِت الميكانيكية (Context موجود، مقروء، صحيح) بلا الحاجة لبيانات مؤسسية لعرضها.

**لا تغيير آخر على أي شاشة** — Catalog/Home/Application Details تبقى بلا أي إشارة للسياق (عامة بطبيعتها، قسم D بوثيقة الـUX الأصلية، لم يتغيّر).

---

# E. Business Rules

| # | القاعدة |
|---|---|
| BR-2A-01 | تبديل السياق لمؤسسة المستخدم ليس عضوًا فيها **يُرفَض صراحة** (403) — يتحقق الـController، لا اعتماد على إخفاء الخيار بالواجهة وحده. |
| BR-2A-02 | جلسة تشير لمؤسسة فقد المستخدم عضويته بها تُصحَّح تلقائيًا لـ"شخصي" بأول طلب لاحق، بصمت، بلا كسر الصفحة. |
| BR-2A-03 | تبديل السياق **لا يغيّر أي بيانات معروضة بهذي المرحلة عدا شريط الاسم نفسه** — لا Organization Subscription موجودة بعد لتتأثر. |
| BR-2A-04 | اختيار سياق مؤسسة **لا يمنح أي صلاحية إدارية** (AD-011) — لا فحص دور يُبنى هنا أصلًا، هذا خارج نطاق 2A بالكامل (2E لاحقًا). |

---

# F. Security

مطابق لقسم L بوثيقة التصميم، مُطبَّق بحدود 2A فقط: تلاعب Cookie/Session بقيمة `active_organization_id` لمؤسسة غير منتمٍ إليها → Middleware (B) يصحّحها تلقائيًا لأي طلب لاحق يقرأها. لا بيانات حسّاسة تُعرَض بناءً على السياق بهذي المرحلة (My Apps لا تزال شخصية بحتة) — فسحة الخطر الفعلية شبه معدومة بـ2A تحديدًا، لكن الآلية نفسها (Middleware + تحقق Controller مزدوج) هي الأساس اللي كل حماية Tenant Isolation لاحقة (2D) تعتمد عليه — لذلك تُبنى بصرامة من الآن.

---

# G. Testing

| # | السيناريو |
|---|---|
| 1 | مستخدم بمؤسسة واحدة يبدّل لها → الجلسة تُحدَّث، شريط My Apps يعكس الاسم |
| 2 | مستخدم يبدّل لمؤسسة **ليس عضوًا فيها** → 403، الجلسة لا تتغيّر |
| 3 | مستخدم يعود لـ"شخصي" → الجلسة تُمسَح |
| 4 | مستخدم بمؤسستين (A، B) يبدّل بينهما بالتتابع → كل تبديل يعكس الاسم الصحيح، لا تراكم/خلط |
| 5 | جلسة تحمل `organization_id` لمؤسسة (محاكاة يدوية بالاختبار) ثم Membership تُحذَف → أول طلب لاحق يصحّح السياق تلقائيًا لـ"شخصي" (BR-2A-02) |
| 6 | مستخدم بلا أي مؤسسة → المبدّل لا يظهر إطلاقًا بالهيدر |
| 7 | Regression: كل صفحات Phase 1a/1b (Catalog، Application Details، My Apps الوظيفة الأساسية) تعمل بلا كسر بوجود الهيدر الجديد |

---

# H. Definition of Done

- [ ] `ActiveOrganizationContext::current()/switchTo()/switchToPersonal()` تعمل وفق العقد أعلاه
- [ ] Middleware يصحّح جلسة غير صالحة تلقائيًا (BR-2A-02، مُختبَر صراحة)
- [ ] مبدّل الهيدر يظهر فقط لمستخدم له مؤسسة واحدة فعلية على الأقل
- [ ] شريط My Apps يعكس السياق الحقيقي
- [ ] لا أي تغيير على بيانات Subscription/Access المعروضة (لا شيء مؤسسي موجود بعد)
- [ ] الاختبارات السبعة أعلاه تمر
- [ ] **صفر Regression** — كل اختبارات Phase 1a/1b (69) تبقى ناجحة

---

# I. Non-Goals (تبقى 🔴 حتى اعتماد منفصل)

Organization Subscription · `SubscriptionSeat` · Organization Access/Authorization · أي تأثير فعلي لتبديل السياق على بيانات معروضة غير شريط الاسم · Billing بأي شكل.

---

# J. Architecture Conflicts / Core Dependencies

**لا تعارض جديد.** هذي المرحلة **تحلّ CD-001 نفسه** (المسجَّل بـ`marketplace-product-ux-architecture.md` قسم U و`marketplace-implementation-specification.md` قسم AF) — لأول مرة الآن مُصرَّح ببنائه فعليًا، بعد فترة كان مسجَّلًا كاعتمادية مؤجَّلة على Core.

---

**ملخص الحالة:** Specification جاهزة للتنفيذ الفوري (مُصرَّح به). نطاق ضيّق جدًا بالتصميم — جلسة + Middleware + Controller بسيط + لمسة UI محدودة، بلا أي قاعدة بيانات جديدة.
