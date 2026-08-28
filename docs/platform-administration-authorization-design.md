# Platform Administration Authorization — Design

**الحالة:** تصميم فقط. **صفر كود، صفر Migration، صفر تعديل على أي ملف تشغيلي.** كل حقيقة هنا مصدرها فحص مباشر للكود الفعلي وقت الكتابة — لا افتراض.
**السبب:** `docs/phase-ol-completion-report.md` اكتشف تعارضًا بين نمطين مختلفين للثقة داخل نفس المشروع (`OrganizationSubscriptionService` تثق بحدود Filament بالكامل، `MembershipService`/`OrganizationLifecycleService` تتطلب Owner Membership حقيقية) — يمنع استخدام Archive/Transfer Ownership فعليًا عبر Filament اليوم. **هذي الوثيقة لا تحل تلك المشكلة الضيّقة فقط — تحلّها بحسم السؤال الأعمق الذي كانت تُخفيه: من يملك حق الدخول لـFilament أصلًا، ومن يملك أي صلاحية بداخله؟**
**المرجع:** `phase-ol-completion-report.md` · `marketplace-access-control-audit.md` §3 (الفجوة الموروثة المذكورة سابقًا، تُحسَم هنا للمرة الأولى فعليًا) · `owner-integrity-hardening-design.md` · `organization-lifecycle-hardening-design.md`.

---

## Executive Summary

**السؤال اللي بدأ بهذا التحقيق ("Archive تفشل لموظف حكم ورقم") كان عرَضًا لمشكلة أعمق بكثير، لا مشكلة بمعزل:**

> **لا يوجد أي مفهوم "موظف حكم ورقم" بالكود اليوم إطلاقًا.** لا عمود `role`/`is_staff` على `users`، لا `canAccessPanel()`، لا أي تقييد. **أي مستخدم مصادَق بالمنصة — شاملة عميل عادي سجَّل حسابًا بنفسه بالأمس عبر `/register` — يقدر يفتح `/admin` اليوم ويصل لكل موارد الإدارة** (المؤسسات، الاشتراكات، الأنظمة القانونية، عناصر المتجر...).

هذا يعني إن الافتراض اللي بُني عليه نمط `OrganizationSubscriptionService` ("Filament = أداة داخلية موثوقة، لا حاجة لفحص إضافي") **لم يكن خاطئًا بمعزل — كان يعتمد على حد فاصل (Perimeter) غير موجود فعليًا بالكود.** الحل الصحيح ليس "اختيار Option A أو B أو C" من التقرير السابق بمعزل — **الحل يبدأ ببناء الحد الفاصل نفسه أولًا (من يدخل Filament؟)، وبعده يصبح اختيار نموذج الثقة داخل Filament قرارًا بسيطًا ومباشرًا.**

**التوصية النهائية (تفصيل كامل بقسم 4):** بناء مفهوم **Platform Staff** حقيقي (حقل + `canAccessPanel()`)، ثم اعتماد **مسار ثنائي للصلاحية** على أفعال Organization الحساسة: **Owner حقيقي بالمؤسسة، أو Platform Staff موثَّق** — أيهما تحقَّق. هذا يحل مشكلة Archive/Transfer فورًا، **بلا إضعاف** أي حماية بناها Phase OI، لأن الحد الفاصل نفسه أصبح حقيقيًا الآن.

---

## 1. Root Cause — الوضع الفعلي اليوم (فحص مباشر، لا افتراض)

### 1.1 — لا مفهوم Staff بالـSchema

```php
// database/migrations/0001_01_01_000000_create_users_table.php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->rememberToken();
    $table->timestamps();
});
```
**لا عمود `role`، لا `is_staff`، لا أي حقل تصنيفي — بحث شامل بكل الـMigrations اللاحقة أيضًا لم يجد إضافة كهذي أبدًا.** كل مستخدم بالنظام (عميل حقيقي سجَّل بنفسه، أو من نفترض إنه "موظف") **صف مطابق تمامًا بنفس الجدول، بلا أي تمييز بنيوي.**

### 1.2 — لا تقييد دخول لـFilament

```php
// app/Providers/Filament/AdminPanelProvider.php
->authMiddleware([
    Authenticate::class,   // ← فقط "مسجَّل دخول"، لا أكثر
]),
```
**لا `FilamentUser` Contract مُطبَّق على `User`، لا `canAccessPanel()` معرَّف بأي مكان.** فحص شامل (`grep -rn "canAccessPanel"`) **لا نتيجة واحدة بكل المشروع.** النتيجة العملية: **أي حساب — بما فيه حساب اختباري عشوائي بلا أي علاقة بحكم ورقم كمنظمة — يقدر يفتح `/admin` بمجرد تسجيل الدخول العادي.**

### 1.3 — نموذجا ثقة متعارضان داخل نفس الكودبيس

| الخدمة | المرحلة | نموذج الثقة |
|---|---|---|
| `OrganizationSubscriptionService::create/changeSeatLimit/cancel` | Phase 2B (تعمل فعليًا اليوم) | **صفر Gate داخلي — تثق بحدود Filament بالكامل** |
| `MembershipService::changeRole/remove/transferOwnership` | Phase OI (هذي الجلسة) | **Gate::authorize صارم — يتطلب Owner/Admin Membership حقيقية للفاعل** |
| `OrganizationLifecycleService::archive/restore` | Phase OL (هذي الجلسة) | نفس أعلاه — Owner حقيقي فقط |

**كلا النموذجين "صحيح" منطقيًا بمعزل — التعارض إنهما يفترضان حدَّين فاصلَين مختلفين تمامًا لنفس نقطة الدخول (Filament) بلا أي قرار موحَّد يحسم أيهما الصائب.**

### 1.4 — جرد كامل لموارد Filament الحالية (لتقييم الأثر الحقيقي، لا افتراضًا)

| المورد | طبيعته | من يُفترَض يديره؟ | يتقاطع مع Organization Roles؟ |
|---|---|---|---|
| `OrganizationResource` (+Memberships/Subscriptions RelationManagers) | إدارة مؤسسات العملاء | حكم ورقم (بمسؤولية اليوم) | ✅ نعم — محور هذي الوثيقة |
| `AppSubscriptionResource` (Legacy) | بيانات اشتراك Legacy شخصي | حكم ورقم (أرشيف إداري، AD-006) | ❌ لا — شخصي بحت |
| `MarketplaceItemResource`/`MarketplaceCategoryResource`/`PartnerResource` | كتالوج المتجر | حكم ورقم (محتوى منصة) | ❌ لا |
| `LawEntryResource`/`LegalUpdateResource`/`CategoryResource` | محتوى بوابة معرفة القانوني | حكم ورقم (محتوى منصة) | ❌ لا |
| `ServiceInterestResource` | طلبات اهتمام العملاء | حكم ورقم (متابعة تسويقية) | ❌ لا |

**النتيجة الحاسمة:** 8 من 9 موارد Filament **لا علاقة لها بمفهوم "عضوية مؤسسة" إطلاقًا** — إدارتها Platform-level بحتة (محتوى، كتالوج، بيانات Legacy). **مشكلة "من يقدر يفعل ماذا" الحقيقية لكل هذي الموارد أبسط بكثير من Organization: إما موظف حكم ورقم يقدر يديرها، أو لا أحد يقدر — لا طيف أدوار وسيط.** فقط `OrganizationResource` تحديدًا يحتاج التمييز الدقيق (Owner/Admin/Member) لأنه المكان الوحيد اللي "صلاحية العميل نفسه" ذات معنى حقيقي.

---

## 2. لماذا الخيارات الثلاثة الأصلية (تقرير Phase OL) كانت غير مكتملة كلها

| الخيار الأصلي | لماذا لم يكن كافيًا بمفرده |
|---|---|
| **A — مطابقة نمط 2B (ثقة Filament كاملة)** | صحيح **فقط لو** الدخول لـFilament نفسه مقيَّد فعليًا — اليوم **أي عميل** يقدر يفتح `/admin`، فـ"ثقة Filament" تعني عمليًا "ثقة أي مستخدم مسجَّل بالمنصة كاملة"، وهذا إضعاف حقيقي لا نظري |
| **B — مفهوم ثقة إدارية جديد** | كان الاتجاه الصحيح جزئيًا، لكنه وُصِف بلا الأساس اللازم له (من هو "الموظف" أصلًا؟) — بلا هذا الأساس، "ثقة إدارية" مجرد اسم آخر لنفس فجوة الخيار A |
| **C — الفاعل دائمًا Owner حقيقي** | يحل الأمان بالكامل، **لكنه يُعطِّل الميزة كليًا** لأي مؤسسة بلا Owner سليم (Org 1/Org 2 تحديدًا) — لا يوجد "نيابة" حقيقية، فعليًا يمنع حكم ورقم من أي تدخل تشغيلي حتى لحالات طارئة مشروعة |

**كل خيار كان يعالج نصف المشكلة.** الحل الكامل يحتاج (أ) حد فاصل حقيقي لـ"من يدخل Filament أصلًا" **ثم** (ب) قرار صلاحية داخل ذاك الحد — بالترتيب، لا أحدهما بمعزل عن الآخر.

---

## 3. Option D — التوصية النهائية (توليف، لا اختيار من الثلاثة الأصلية بمعزل)

### 3.1 — الأساس: Platform Staff حقيقي

- حقل تصنيفي جديد على `users` (تصميم لا تنفيذ — الاسم/الشكل التقني تفصيل لاحق: `is_platform_staff` Boolean، أو `role` Enum بقيمتين `customer`/`platform_staff`، **قرار مفتوح رقم 1**).
- `User implements FilamentUser` + `canAccessPanel(Panel $panel): bool` يتحقق من هذا الحقل — **يمنع أي عميل عادي من فتح `/admin` إطلاقًا من الأساس**، بصرف النظر عن أي منطق داخلي لاحق.

### 3.2 — مسار الصلاحية على أفعال Organization الحساسة: Owner **أو** Staff

```
Authorization(action, organization, actor) :=
    ActorIsOwnerMember(actor, organization)     // المسار الأصلي، Phase OI/OL كما هو تمامًا
    OR
    ActorIsPlatformStaff(actor)                  // المسار الجديد، يحل مشكلة Filament
```

**هذا لا يستبدل منطق Phase OI/OL — يضيف مسارًا ثانيًا موازيًا.** لو بُنيت مستقبلًا واجهة ذاتية حقيقية للعميل (خارج Filament تمامًا)، فحص "Owner Member" يبقى يعمل بلا أي تغيير — يحمي من عضو غير مخوَّل بنفس القوة المُختبَرة فعليًا بـ16 اختبار Phase OI. الجديد فقط: **موظف موثَّق فعليًا (بعد حل 3.1) يقدر يتجاوز شرط العضوية تحديدًا لأنه يعمل من موقع مسؤولية إدارية موثَّقة، لا لأن الفحص أُزيل.**

### 3.3 — لماذا هذا يحل مشكلة Org 1/Org 2 (خلاف الخيار C الأصلي)

مؤسسة بلا Owner حقيقي (Org 1/Org 2) **لا يوجد أحد يقدر يؤرشفها عبر المسار الأول (Owner)** — لكن **Platform Staff يقدر**، لأنها بالضبط نوع الحالة الاستثنائية اللي تحتاج تدخلًا إداريًا (تذكير: هذي المؤسستان بالذات محل قرار إداري معلَّق أصلًا بـ`owner-integrity-hardening-design.md`، لم تُصلَحا تلقائيًا بقرارك السابق). **لا تناقض مع ذاك القرار** — المسار الثاني (Staff) لا "يُصلِح" التضارب، فقط يسمح بفعل تشغيلي طارئ (أرشفة مؤسسة معطوبة الملكية، مثلًا) بلا انتظار حل التضارب أولًا.

### 3.4 — لماذا هذا لا يُضعِف Phase OI الأمنية

الاختبارات الستة الموجودة فعليًا لـAuthorization (`MembershipServiceTest`) **تبقى كلها صحيحة وتعمل بلا أي تعديل** — تتحقق من "غير Owner/Admin حقيقي يُرفَض"، وهذا يبقى صحيحًا **لأي فاعل ليس Owner ولا Staff موثَّق**. الإضافة الوحيدة: اختبار جديد يثبت "Staff موثَّق يُقبَل حتى بلا Membership" — توسيع للتغطية، لا نقض لها.

---

## 4. Authorization Matrix — كل مورد × كل فاعل

### 4.1 — الموارد الثمانية غير المرتبطة بـOrganization (قسم 1.4)

| المورد | Platform Staff | Customer (غير Staff) |
|---|---|---|
| `AppSubscriptionResource`, `MarketplaceItemResource`, `MarketplaceCategoryResource`, `PartnerResource`, `LawEntryResource`, `LegalUpdateResource`, `CategoryResource`, `ServiceInterestResource` — **كل فعل (View/Create/Edit/Delete)** | ✅ (عبر `canAccessPanel` فقط — Filament بالكامل غير مرئية أصلًا لغير Staff) | ❌ **لا وصول لـ`/admin` إطلاقًا** |

### 4.2 — `OrganizationResource` وما يتفرَّع منه (المحور الحقيقي)

| Action | Platform Staff | Owner (المؤسسة المستهدَفة) | Admin (نفس المؤسسة) | Member آخر | غير عضو/Customer |
|---|---|---|---|---|---|
| View قائمة المؤسسات / تفاصيل مؤسسة | ✅ | ⚠️ **لا يوجد اليوم مسار ذاتي أصلًا خارج Filament — نظري فقط** | ⚠️ نظري | ⚠️ نظري | ❌ (`canAccessPanel`) |
| Create Organization | ✅ | — (لا مفهوم "ينشئ نفسه" منطقيًا) | — | — | ❌ |
| Archive Organization | ✅ (المسار الجديد 3.2) | ✅ (المسار الأصلي، BR الحالي) | ❌ | ❌ | ❌ |
| Restore Organization | ✅ | ✅ | ❌ | ❌ | ❌ |
| Transfer Ownership | ✅ | ✅ (Owner المصدر تحديدًا) | ❌ | ❌ | ❌ |
| Change Member Role (`manageMembers`) | ✅ | ✅ | ✅ (بالفعل، BR-2B-02) | ❌ | ❌ |
| Remove Member | ✅ | ✅ | ✅ | ❌ | ❌ |
| Add Member | ✅ | ⚠️ **لا Gate اليوم أصلًا** (خارج نطاق Phase OI، راجع مواصفتها) | ⚠️ نفسه | ❌ | ❌ |
| Create/Cancel Organization Subscription (`manageSubscription`) | ✅ (المسار الجديد — **يحتاج تعديلًا فعليًا لاحقًا، اليوم `OrganizationSubscriptionService` بلا Gate إطلاقًا، راجع قسم 6**) | ✅ (Owner فقط، BR-2B-01) | ❌ | ❌ | ❌ |
| Assign/Release Seat (`manageSeats`) | ✅ (نفس الملاحظة) | ✅ | ✅ (BR-2B-02) | ❌ | ❌ |

**الأعمدة "Owner"/"Admin"/"Member" بأعلاه تبقى **نظرية اليوم** لأي فعل غير موجود له مسار ذاتي حقيقي خارج Filament (كل شيء فعليًا عبر Filament اليوم = Staff فقط عمليًا) — الجدول يوثّق **القاعدة الصحيحة المصمَّمة**، لا الواقع التشغيلي الحالي (المُقتصِر على Staff عبر Filament لكل شيء اليوم).**

---

## 5. الأثر على كود Phase OI/OL الموجود (وصف فقط، لا تنفيذ)

| الملف | التغيير الوصفي المطلوب لاحقًا (لو اعتُمد) |
|---|---|
| `app/Models/User.php` | `implements FilamentUser` + `canAccessPanel()` + Accessor/علاقة لتحديد `isPlatformStaff()` |
| Migration جديدة | عمود تصنيفي على `users` (القرار المفتوح 1) |
| `App\Policies\OrganizationPolicy` | كل تابع (`manageSubscription`, `manageSeats`, `manageMembers`, `transferOwnership`, `archive`, `restore`) يضيف شرط `OR $user->isPlatformStaff()` |
| `App\Services\MembershipService`/`OrganizationLifecycleService` | **لا تغيير على منطقهما الداخلي** — كلاهما يستدعي `Gate::forUser($actor)->authorize(...)`، والتوسيع يحدث بمستوى الـPolicy فقط (نقطة واحدة للتغيير، لا تكرار) |
| `App\Services\OrganizationSubscriptionService` | **يحتاج إضافة Gate::authorize مماثل لأول مرة** (اليوم بلا أي فحص) — **هذا تغيير سلوك حقيقي (تشديد، لا تخفيف)**، يستحق قسمًا مستقلًا أدناه لأنه يمس Phase 2B الموجودة فعليًا |
| Filament Resources | **لا تغيير** — `Gate::forUser($actor)->authorize()` بالخدمات يبقى نقطة التحقق الوحيدة، Filament تبقى "غبية" (لا تعرف القواعد، فقط تستدعي الخدمة وتعرض النتيجة) |

---

## 6. ملاحظة حرجة مستقلة: `OrganizationSubscriptionService` اليوم بلا أي فحص إطلاقًا

**اكتشاف جانبي بهذا التحقيق (لا علاقة مباشرة بمشكلة Archive الأصلية، لكنه أخطر منها):** بما إن لا تقييد دخول لـFilament (قسم 1.2)، وبما إن `OrganizationSubscriptionService::create/changeSeatLimit/cancel` **بلا أي `Gate::authorize` داخلي**، فالوضع الفعلي اليوم هو: **أي عميل مسجَّل بالمنصة يقدر يفتح `/admin` وينشئ/يُلغي اشتراكًا مؤسسيًا لأي مؤسسة، بما فيها مؤسسات لا علاقة له بها إطلاقًا.** هذا **أخطر من مشكلة Archive** (اللي كانت "الفعل يرفض حتى للفاعل الصحيح") — هنا **الفعل يُقبَل من فاعل خاطئ تمامًا**. **لم يُكتشَف هذا سابقًا لأن كل تدقيق أمني سابق (`marketplace-access-control-audit.md`) افترض "Filament مستخدموها موثوقون" كمقدّمة مقبولة، لا فرضية تحتاج فحصًا.** يستحق إغلاقًا بنفس هذا التصميم (قسم 5، السطر الخاص بهذا الملف) — **ليس حصرًا على Archive/Transfer**.

---

## 7. ما لا تحله هذي الوثيقة (نطاق متعمَّد الحصر)

- ❌ **لا تحدد آلية تعيين Staff فعليًا** (من يقرر مين موظف؟ عبر Filament نفسه؟ Seeder؟ Tinker يدوي؟) — قرار تنفيذي لاحق.
- ❌ **لا تبني واجهة ذاتية للعميل** — الأعمدة "Owner/Admin/Member" بالمصفوفة تبقى نظرية لحد وجود مسار ذاتي حقيقي خارج Filament (خارج نطاق أي مرحلة حالية).
- ❌ **لا تحسم مصير Org 1/Org 2 المتضاربتين** — يبقى قرارًا إداريًا منفصلًا كما تقرَّر سابقًا.
- ❌ **لا Header/Dashboard/Marketplace UI** — لا علاقة.

---

## Open Decisions

### Critical
1. **شكل حقل Staff التقني:** Boolean بسيط (`is_platform_staff`)، أم `role` Enum يفتح الباب لاحقًا لأدوار داخلية متعددة (Support/Finance/SuperAdmin)؟ **التوصية: Boolean بسيط الآن** (Future-ready ≠ Future-built — لا حاجة فعلية لأدوار داخلية متعددة اليوم، لا طلب لها).
2. **من يُعتبَر Staff أول تعيين؟** الأقرب عمليًا: `admin@marefa.local` (الحساب المُستخدَم بكل تحقق إداري بالمشروع لحد الآن) — **لكن هذا قرارك، لا افتراضًا مني.**
3. **هل `OrganizationSubscriptionService` (قسم 6) تُصلَح بنفس هذا التنفيذ، أم تحتاج تصريحًا منفصلًا لأنها تمس Phase 2B المُعتمَدة سابقًا؟**

### High
4. هل `canAccessPanel()` يُبنى **أولًا** بمعزل (يغلق الفجوة الأخطر — دخول العملاء لـ`/admin` أصلًا) **قبل** أي تعديل على Policies التفصيلية، أم الاثنان معًا بتنفيذ واحد؟

**لا قرار تنفيذي اتُّخذ — تصميم فقط، بانتظار اعتمادك على الاتجاه العام (Option D) والأسئلة أعلاه قبل أي كود.**
