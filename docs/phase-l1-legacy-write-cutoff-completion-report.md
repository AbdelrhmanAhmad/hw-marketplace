# L1 — Legacy Write Cutoff — تقرير الإكمال

**الحالة:** 🟢 منفَّذ ومُتحقَّق منه فعليًا (كود حقيقي + قاعدة بيانات حقيقية + متصفح حقيقي)
**النطاق المعتمَد:** إيقاف كل كتابة جديدة إلى `app_subscriptions` من مسارات التطبيق الطبيعية — بدون Synchronization بديلة، بدون لمس Marketplace Logic، بدون حذف/Migration/Backfill.
**المرجع:** `legacy-subscription-l1-spec.md` (الأسئلة A-E) · `legacy-subscription-closure-plan.md` (L0-L4) · AD-006 · AD-013 · AD-014.
**معيار القبول المعتمَد من المستخدم:** *"لا يوجد Application Flow طبيعي جديد يكتب إلى `app_subscriptions`، بينما تبقى جميع وظائف حكم ورقم الحالية وMarketplace الحالية تعمل كما كانت، ولا يحدث أي تغيير مرئي سلبي للمستخدم."* — الجزء الأول محقَّق بالكامل بدليل قاعدة بيانات حقيقي. الجزء الثاني محقَّق **بتحفظ واحد موثَّق أدناه** (§4 و§11) وليس مخفيًا.

---

## 1. الملفات التي تغيّرت

| الملف | التغيير |
|---|---|
| `app/Http/Controllers/Auth/RegisteredUserController.php` | حذف سطر `FreeAppProvisioner::ensure($user);` + حذف `use App\Support\FreeAppProvisioner;` |
| `app/Http/Controllers/HomeController.php` | حذف الشرط الكامل `if (auth()->check()) { FreeAppProvisioner::ensure(auth()->user()); }` + حذف الـ`use` |
| `app/Http/Controllers/DashboardController.php` | حذف سطر `FreeAppProvisioner::ensure($user);` + حذف الـ`use` |
| `tests/Feature/Marketplace/CoreRegressionTest.php` | تحديث اختبار كان يفترض السلوك القديم (auto-grant) ليعكس السلوك الجديد صراحة، مع تعليق يشرح السبب ويشير لهذا التقرير |
| `tests/Feature/Marketplace/LegacyWriteCutoffTest.php` | **ملف جديد** — 4 اختبارات Write Guard |

**لم يتغيّر أي سطر آخر بأي ملف آخر.** لا Migration، لا Schema، لا Model، لا View (باستثناء ما ينتج تلقائيًا من عرض الحالة الفارغة الموجودة أصلًا بـ`dashboard.blade.php` — الشرط `@if ($subscribedApps->isEmpty())` كان موجودًا مسبقًا، لم أُضِف أو أُعدِّل أي Blade).

---

## 2. كل Legacy Writer تم إيقافه

الكاتب الوحيد بكل الكودبيس فعليًا هو `FreeAppProvisioner::ensure()`، ونقاط استدعائه الثلاث المؤكَّدة بـ`legacy-subscription-l1-spec.md` (القسم A):

1. `RegisteredUserController::store()` — عند كل تسجيل حساب جديد
2. `HomeController::index()` — عند كل زيارة مصادَقة لـ`/marefa`
3. `DashboardController::index()` — عند كل زيارة مصادَقة لـ`/dashboard`، بما فيها كل تسجيل دخول عادي (Laravel Breeze يوجّه افتراضيًا لـ`/dashboard`)

**الكاتب الوحيد الباقي عمدًا:** `Filament\Resources\AppSubscriptionResource` (فعل يدوي إداري من طاقم حكم ورقم بلوحة `/admin`) — خارج نطاق L1 كليًا حسب القسم A من الوثيقة، ولا علاقة له بالكتابة التلقائية.

**لم تُحذف `FreeAppProvisioner` نفسها** — الكلاس باقٍ كما هو بالكامل، غير مستخدَم الآن من أي Controller، لضمان Rollback بسطر واحد لكل ملف لو احتجنا التراجع (راجع §12).

---

## 3. لماذا كان آمنًا إيقافه

- **القسم C من `legacy-subscription-l1-spec.md`** أثبت إن Free Application Provisioning وظيفة Marketplace-domain قديمة، ليست Core Platform — القرار الصحيح بمكانها الصحيح موجود اليوم فعليًا (`SubscriptionService`)، لكن L1 **لا يستبدلها** به (بند 2 من تعليماتك — ممنوع Synchronization) — فقط يوقف الكتابة القديمة.
- `AppSubscription::firstOrCreate()` كان الاستدعاء الوحيد الذي يكتب لهذا الجدول عبر هذي المسارات الثلاثة — لا منطق آخر يعتمد على إن الكتابة تحدث بلحظة الطلب نفسها (لا Queue Job، لا Event Listener خارجي يستمع لهذا الفعل).
- `DashboardController` ما زال يقرأ من نفس الجدول (`$user->subscriptions()->active()`) بنفس الطريقة تمامًا — القراءة لم تُمَس، فقط التوليد التلقائي للبيانات الجديدة توقف.

---

## 4. أي Core-adjacent files تم لمسها

**الثلاثة كلها Core-adjacent فعليًا** كما حذّرت الوثيقة السابقة صراحة (القسم B):
- `RegisteredUserController.php` — Auth scaffolding (Laravel Breeze)
- `HomeController.php` — نقطة الدخول الرئيسية لبوابة معرفة
- `DashboardController.php` — Core Platform Dashboard

**نطاق اللمس بكل ملف: سطر واحد أو سطرين فقط (حذف استدعاء + حذف import)** — لا إعادة هيكلة، لا تغيير بمنطق التسجيل/التحقق/التوجيه/الاستعلامات الأخرى. تحقَّق بـ`php -l` على الثلاثة (بدون أخطاء) وبتشغيل المسارات فعليًا عبر HTTP حقيقي (§6).

---

## 5. قبل/بعد Login Flow

```
قبل L1:
  Register/Login → redirect(/dashboard) → DashboardController::index()
        ↓
  FreeAppProvisioner::ensure($user)  ← كتابة AppSubscription لكل تطبيق مجاني متاح
        ↓
  عرض $subscribedApps (يتضمن بوابة معرفة تلقائيًا حتى لو المستخدم لم يفعل شيئًا)

بعد L1:
  Register/Login → redirect(/dashboard) → DashboardController::index()
        ↓
  (لا كتابة)
        ↓
  عرض $subscribedApps من نفس القراءة القديمة تمامًا — فارغة لمستخدم جديد لم يُنشأ له سجل قط
```

**التوجيه نفسه لم يتغيّر حرفيًا** — `redirect(route('dashboard', absolute: false))` بعد التسجيل، و`redirect()->intended(route('dashboard'))` من Breeze بعد الدخول، كلاهما كما كان بالضبط. الدليل: `REGISTER_HTTP_CODE=302`, `REGISTER_REDIRECT=http://127.0.0.1:8000/dashboard` (نفس القيمة قبل وبعد التعديل، اختُبِر على الخادم الحي).

---

## 6. Database Evidence (تشغيل حقيقي على الخادم الحي `php artisan serve`، لا Simulation)

### سيناريو أ — مستخدم جديد تمامًا (curl حقيقي، جلسة حقيقية، CSRF حقيقي)

| الجدول | قبل | بعد رحلة كاملة (Register→Dashboard→Marefa→Marketplace→My Apps) |
|---|---|---|
| `app_subscriptions` | 4 | **4 (بدون تغيير)** |
| `subscriptions` (النظام الجديد) | 5 | **5 (بدون تغيير)** |
| `access_assignments` (النظام الجديد) | 6 | **6 (بدون تغيير)** |
| `users` | 8 | 9 (طبيعي — مستخدم جديد فعليًا انضم) |

```
new user id: 9
legacy rows for new user: 0
hasActiveSubscription('marefa'): false
```

### سيناريو ب — مستخدم "قديم الطراز" (له سجل Legacy من قبل L1، محاكى يدويًا لتمثيل مستخدم حقيقي سابق)

```
LOGIN_HTTP_CODE=302
LOGIN_REDIRECT=http://127.0.0.1:8000/dashboard   ← نفس التوجيه القديم تمامًا
DASHBOARD_HTTP_CODE=200
→ صفحة /dashboard تعرض "بوابة معرفة" كما كانت (السجل القديم لم يُمَس)
→ app_subscriptions العدد الكلي لم يزد بأي سطر إضافي بسبب هذا الدخول
```

**هذا يثبت مباشرة إن المستخدمين الحاليين (اللي عندهم سجل Legacy فعلًا) تجربتهم على Dashboard لم تتغيّر حرفيًا شيئًا — لا اختفاء، لا تأخير، لا خطأ.**

كل حسابات الاختبار المُستخدَمة بهذا التحقق (`l1-live-evidence@`, `l1-existing-user@`, وحساب Playwright) **حُذِفت فورًا بعد التحقق** — قاعدة البيانات أُعيدت لحالتها الأصلية تمامًا (8 مستخدمين، 4 سجلات Legacy) قبل كتابة هذا التقرير.

---

## 7. الاختبارات

| الملف | الاختبارات الجديدة/المعدَّلة | الغرض |
|---|---|---|
| `tests/Feature/Marketplace/LegacyWriteCutoffTest.php` (جديد) | `test_registration_does_not_write_to_legacy_app_subscriptions` | تسجيل حساب حقيقي عبر `POST /register`، تأكيد `AppSubscription::count()` ثابت |
| | `test_marefa_home_visit_does_not_write_to_legacy_app_subscriptions` | زيارة `/marefa` مصادَقة، نفس التأكيد |
| | `test_dashboard_visit_does_not_write_to_legacy_app_subscriptions` | زيارة `/dashboard`، نفس التأكيد |
| | `test_full_new_user_journey_leaves_legacy_and_new_tables_untouched_without_explicit_marketplace_action` | **اختبار Write Guard الشامل** المطلوب صراحة — رحلة كاملة (تسجيل→Dashboard→Marketplace→My Apps)، تأكيد ثلاثة جداول (`app_subscriptions`, `subscriptions`, `access_assignments`) بلا أي تغيير، **بدون منع أدوات Migration المستقبلية** (الاختبار لا يضع Guard بمستوى قاعدة البيانات، فقط يتحقق من سلوك الـFlow الحالي — أدوات L2 مستقبلية حرة بالكتابة صراحةً) |
| `tests/Feature/Marketplace/CoreRegressionTest.php` (معدَّل) | `test_authenticated_dashboard_still_loads` (مُعاد تسمية، بدون افتراض auto-grant) | Dashboard يحمّل بنجاح (200) بمعزل عن Legacy |
| | `test_dashboard_no_longer_auto_grants_legacy_free_app_subscription` (جديد) | يوثّق صراحة السلوك الجديد المقصود، بتعليق يشير لهذا التقرير |

### Regression — لم يُكسَر شيء

كل ملفات الاختبار الموجودة مسبقًا (Auth، Marketplace، Organization، OrganizationContext) عملت **بدون أي تعديل** غير الاثنين أعلاه — تشمل:
- `Auth/RegistrationTest.php`, `AuthenticationTest.php`, `EmailVerificationTest.php`, `PasswordResetTest.php`, `PasswordConfirmationTest.php`, `PasswordUpdateTest.php`
- `Marketplace/CatalogRepositoryTest.php`, `CompatibilityLayerTest.php`, `CatalogParityTest.php`, `EntitlementResolverTest.php`, `SubscriptionServiceTest.php`, `AccessFlowTest.php`, `AuditTrailTest.php`
- `Organization/*` (5 ملفات) و`OrganizationContext/ActiveOrganizationContextTest.php`

## 8. عدد الاختبارات وAssertions

```
{"tool":"phpunit","result":"passed","tests":109,"passed":109,"assertions":305,"duration_ms":3459}
```

(كان 104 اختبار/289 assertion قبل L1 — الزيادة 5 اختبارات/16 assertion بالضبط تعادل الاختبارات الجديدة أعلاه، صفر اختبار فشل أو حُذف.)

---

## 9. Regression — تحقق بصري حقيقي (Playwright، خادم حي)

رحلة كاملة على `php artisan serve` الحقيقي: Register → Dashboard → `/marefa` → `/marketplace` → `/my/apps` → `/laws` → `/bookmarks`.

**النتيجة: `ERROR_COUNT=0`** — صفر console error، صفر pageerror، صفر استجابة HTTP ≥500 عبر كامل الرحلة.

لقطات محفوظة تؤكد:
- `/dashboard` (مستخدم جديد) — يحمّل بنجاح، يعرض حالة "تطبيقاتي" الفارغة الموجودة أصلًا بالتصميم (رابط "تصفّح متجر التطبيقات")
- `/marefa` — يعمل بكامل محتواه (بحث، أنظمة، تحديثات، حاسبة) بدون أي تغيير
- `/marketplace` — يعرض بوابة معرفة ببادج "مجاني" + "متاحة الآن" بشكل صحيح تمامًا عبر منطق Phase 1b الجديد (`marketplaceSubscriptions`) — **مستقل كليًا عن Legacy**، يثبت عمليًا إن منطق Marketplace لم يُمَس إطلاقًا
- `/my/apps`, `/laws`, `/bookmarks` — تحمّل بنجاح (200) بدون أي أخطاء

---

## 10. ما **لم** يتم تغييره (بالحرف الواحد كما طلبت)

- ❌ لم يُحذف جدول `app_subscriptions`
- ❌ لم يُحذف Model `AppSubscription`
- ❌ لم تُحذف أي بيانات موجودة (4 سجلات Legacy الأصلية باقية كما هي تمامًا)
- ❌ لا Backfill بأي شكل
- ❌ لا Migration لنقل بيانات
- ❌ لا تغيير Schema
- ❌ لا Synchronization بين النظامين (لا كتابة تلقائية بديلة للنظام الجديد محل الـLegacy)
- ❌ لم يتغيّر `SubscriptionService`, `AccessAssignment`, `EntitlementResolver`, Organization Access, Seats, Marketplace Catalog, My Apps — **صفر سطر بأي منها**
- ❌ لم يتغيّر Login Flow نفسه (لا Redirect جديد، لا خطوة إضافية، لا صفحة وسيطة)
- ❌ لم تُحذف `FreeAppProvisioner` — باقية كاملة، غير مستخدَمة فقط

---

## 11. أي مخاطر متبقية — **الاكتشاف الأهم بهذا التقرير**

**تغيير مرئي حقيقي واحد، مقصود بنيويًا وليس عرَضيًا، يستحق تأكيدك الصريح قبل اعتبار L1 "بلا أي أثر":**

> **مستخدم جديد يسجّل بعد هذا التعديل لن يرى بوابة معرفة مُفعَّلة تلقائيًا بلوحة "تطبيقاتي" — سيرى الحالة الفارغة بدلًا منها، ويحتاج زيارة `/marketplace` وتفعيلها بنفسه (خطوة واحدة، موجودة مسبقًا منذ Phase 1b).**

**لماذا هذا حدث رغم قاعدة "Preserve Existing User Experience":**
تعليماتك حدّدت بندين متعارضين ظاهريًا لنفس الحالة: (أ) "لا تعمل Synchronization... نريد مصدرين منفصلين مؤقتًا" و(ب) "لا اختفاء وظيفة". تنفيذ (أ) حرفيًا — إيقاف الكتابة بلا بديل — ينتج عنه بالضرورة اختفاء الأثر الوظيفي لـ(ب) **لمستخدم جديد تحديدًا فقط** (وأكدت §6 إن المستخدمين الحاليين اللي عندهم سجل مسبق لا يتأثرون إطلاقًا). لم أخترع حلًا وسطًا (مثل استدعاء `SubscriptionService::subscribeUserToFreeItem()` بدلًا من Legacy) لأن هذا **بالضبط** ما وثيقة `legacy-subscription-l1-spec.md` (سطرها الأخير) اقترحته وتعليماتك الأخيرة نقضته صراحة ببند "لا Synchronization" — احترمت النقض الأحدث ولم أخترع قرارًا من نفسي.

**هذا ليس Bug — هو النتيجة المنطقية الوحيدة لـ"Stop Writes بلا Synchronization"، وموثَّق هنا بدل أن يُكتشَف لاحقًا بالصدفة.**

**مخاطر تقنية أخرى (منخفضة):**
- طالما `FreeAppProvisioner` باقية بالكود بدون استدعاء، أي مطوّر مستقبلي قد يستدعيها بالخطأ ظنًا إنها لا تزال الآلية الصحيحة — يستحق توثيقًا داخل الكلاس نفسه أو إزالته لاحقًا بـL3/L4 (خارج نطاق L1 الحالي).
- `DashboardController` ما زال يعتمد حصريًا على القراءة القديمة (`$user->subscriptions()`) لعرض "تطبيقاتي" — لا يعرض أي اشتراك جديد أُنشئ عبر `SubscriptionService` (Phase 1b/2B) بهذي الصفحة تحديدًا. هذا **قرار Path B السابق نفسه** (لم يتغيّر بـL1)، مذكور هنا فقط لاكتمال الصورة، لا كاكتشاف جديد.

---

## 12. Rollback Procedure

لو احتجت التراجع الفوري عن L1 بالكامل:

1. أعِد سطر `use App\Support\FreeAppProvisioner;` وسطر الاستدعاء بكل ملف من الثلاثة (الكود محذوف فقط، غير مُعاد هيكلته — نسخة `git diff` أو نسخة هذا التقرير كافية لإعادة اللصق الحرفي).
2. لا حاجة لأي إجراء على قاعدة البيانات — لا Migration نُفِّذت، لا بيانات تغيّرت.
3. احذف `tests/Feature/Marketplace/LegacyWriteCutoffTest.php` (اختياري — لن يفشل حتى لو بقي، لكنه سيصبح بلا معنى).
4. أعِد `CoreRegressionTest::test_dashboard_no_longer_auto_grants_legacy_free_app_subscription` إلى نسختها القديمة (assertTrue بدل assertFalse) إن رجعنا فعليًا.

**الوقت التقديري للـRollback الكامل: أقل من 5 دقائق، صفر مخاطر بيانات.**

---

## الخلاصة

L1 مُنفَّذة بأضيق نطاق ممكن كما طلبت حرفيًا: **سطر واحد أو سطران محذوفان بكل ملف من ثلاثة ملفات فقط**، لا شيء آخر تغيّر بالكود. تحقَّق بدليل قاعدة بيانات حقيقي (قبل=بعد على ثلاثة جداول عبر HTTP حقيقي)، و109 اختبار آلي ناجح، وتحقق بصري حقيقي بمتصفح حقيقي بلا أي خطأ. **الاكتشاف الوحيد المستحق قرارك الصريح** هو التغيير المرئي لمستخدم جديد فقط بلوحة Dashboard (§11) — بانتظار تأكيدك إنه مقبول قبل اعتبار L1 مُغلقة نهائيًا.

**L2, L3, L4, 2C — لا تزال 🔴 كما حدّدت، لم أبدأ بأي منها.**
