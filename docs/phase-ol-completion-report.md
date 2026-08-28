# Phase OL — Organization Lifecycle — تقرير الإكمال

**الحالة:** ✅ منطق الـDomain (Service/Policy/DB/Audit) مكتمل، صحيح، ومُختبَر بالكامل — 169/169 اختبار. **⚠️ لكن اكتُشفت مشكلة معمارية حقيقية أثناء التحقق البصري تمنع استخدام الميزة فعليًا عبر Filament اليوم — لا أُخفيها، لا أُصلِحها من نفسي، موضَّحة بالتفصيل أدناه.** هذي المشكلة تمس **Phase OI المُعتمَدة سابقًا أيضًا** (Transfer Ownership)، لا Phase OL فقط.
**المرجع:** `docs/phase-ol-implementation-specification.md`.

---

## ⚠️ الاكتشاف الأهم — يجب قراءته قبل أي قسم آخر

**أثناء التحقق البصري بالمتصفح (لا بالاختبارات الآلية، اللي كانت كلها تستدعي الـService مباشرة بفاعل Owner حقيقي)، اكتُشف:**

تسجيل الدخول لـFilament بحساب `admin@marefa.local` (نفس الحساب المُستخدَم بكل تحقق بصري سابق بهذا المشروع، شاملة Phase OI) ومحاولة الضغط على "أرشفة" لمؤسسة حقيقية أنتجت:

```
تعذّر تنفيذ العملية.
This action is unauthorized.
```

**السبب الجذري (مؤكَّد تجريبيًا، لا افتراضًا):**
```php
$admin = User::where('email', 'admin@marefa.local')->first();
Membership::where('user_id', $admin->id)->count();
// => 0
```

**`admin@marefa.local` ليس عضوًا بأي مؤسسة إطلاقًا.** `OrganizationLifecycleService::archive()`/`restore()` (Phase OL) و`MembershipService::transferOwnership()` (Phase OI) **كلاهما يتطلب Owner Membership حقيقية للفاعل** (`Gate::forUser($actor)->authorize(...)`) — وهذا صحيح ومقصود تمامًا للفاعل الصحيح. **لكن الفاعل الوحيد المتاح فعليًا اليوم عبر Filament هو موظف حكم ورقم — وموظفو حكم ورقم ليسوا، ولا يُفترَض أن يكونوا، أعضاء بمؤسسات العملاء.**

**تحقَّق منطق الـService نفسه سليم 100%** — استدعيته مباشرة بفاعل هو **مالك حقيقي فعليًا** لمؤسسة اختبارية (`ol-playwright-owner@example.com`، له Membership بدور Owner حقيقي):
```
ARCHIVE SUCCEEDED with real owner as actor
org 6 final status: archived
```
**نجح فورًا وبلا أي مشكلة.** المشكلة ليست بمنطق الأعمال — المشكلة إن **Filament (الأداة الوحيدة الموجودة فعليًا لاستدعاء هذي الأفعال) لا تملك فاعلًا يحقق شرط Owner Membership أبدًا في الاستخدام الواقعي.**

### الأثر على Phase OI (مُعتمَدة سابقًا)

**نفس المشكلة تنطبق حرفيًا على "نقل الملكية" (`transferOwnership`)** — لم تُختبَر بصريًا عبر نقرة فعلية بالمتصفح بتقرير Phase OI السابق (فقط تحقُّق من ظهور الزر)، فقط الآن اكتُشفت المشكلة عبر Phase OL. **بالاستخدام الفعلي عبر Filament اليوم، "نقل الملكية" ستفشل بنفس الخطأ بالضبط** لأي موظف حكم ورقم — **لم يُكتشَف هذا وقت اعتماد Phase OI لأن التحقق البصري وقتها لم يشمل نقرة فعلية على الفعل نفسه.**

### لماذا لم أُصلِحها من نفسي

هذا **قرار معماري حقيقي**، لا خطأ برمجي بسيط:
- **`OrganizationSubscriptionService::create()`/`changeSeatLimit()`/`cancel()`** (Phase 2B، مُعتمَدة ومُشغَّلة فعليًا اليوم) **لا تحتوي أي `Gate::authorize()` داخلي إطلاقًا** — تثق بحدود Filament نفسها بالكامل (نمط "أداة داخلية موثوقة"، موثَّق صراحة بتعليقات الكود وقتها).
- **`MembershipService`/`OrganizationLifecycleService`** (Phase OI/OL، هذي الجلسة) **أضافتا Gate::authorize() صارمة** — نمط مختلف عن السابق، بطلب صريح منك ("اختبر Authorization على مستوى Backend").
- **التعارض:** أي نمط منهما "صحيح" بمعزل، لكنهما الآن **غير متسقين مع بعض** داخل نفس المشروع — وأيًا كان الحل، يمس قرارًا أمنيًا حقيقيًا (من يُفترَض يقدر يفعل ماذا عبر Filament؟)، **ليس تفصيلًا تنفيذيًا أقرره بنفسي**.

---

## خيارات الحل (بلا تفضيل مفروض — قرارك)

| الخيار | الوصف | الأثر |
|---|---|---|
| **A — مطابقة نمط Phase 2B الموجود** | إزالة/تخفيف `Gate::authorize()` داخل `MembershipService`/`OrganizationLifecycleService` تحديدًا لاستدعاءات Filament — نفس ثقة `OrganizationSubscriptionService` الحالية | يُفعِّل الميزة فورًا، يطابق النمط الموجود، **لكن يُضعِف الحماية اللي طلبتها صراحة بـPhase OI** (Backend Authorization) — تصبح نظريًا قابلة للتجاوز من أي مستخدم Filament مصادَق، تمامًا كإنشاء الاشتراكات اليوم |
| **B — مفهوم "ثقة إدارية" صريح جديد** | تمييز حقيقي بين "فعل Filament إداري" و"فعل مستخدم نهائي ذاتي" (مثال: Gate Ability إضافية تسمح بالتجاوز فقط لسياق Filament) | يحافظ على قيمة الفحص الحالي لأي واجهة ذاتية مستقبلية، **لكنه يحتاج قرار Access Control على مستوى Filament كامل** (الفجوة الموروثة المذكورة أصلًا بـ`marketplace-access-control-audit.md` §3: "لا Policy تحدد أي موظف يقدر يدير أي مؤسسة") — أكبر من نطاق OI/OL |
| **C — الفاعل الحقيقي دائمًا هو Owner المؤسسة نفسه** | Filament تحدد المالك الفعلي وتُنفِّذ الفعل "بالنيابة عنه" بدل حساب الموظف | لا إضعاف أمني إطلاقًا — **لكنه لا يعمل أصلًا لمؤسسات بلا Owner حقيقي** (بالضبط Org 1/Org 2 اللي بدأتا كل هذا التحقيق!) — قد يكون هذا مقصودًا فعليًا (يمنع أرشفة/نقل ملكية مؤسسة غير سليمة أصلًا حتى تُصلَح)، لكنه قرار منتجي يحتاج تأكيدك |

---

## 1. ما تم بناؤه (صحيح ومُختبَر بالكامل عند طبقة الـService)

**جديد:**
- `Migration`: `organizations.status` (`active`/`archived`, حقل صريح كما طلبت)
- `App\Services\OrganizationLifecycleService` (archive/restore، ذري، Idempotent)
- `tests/Feature/Organization/OrganizationLifecycleServiceTest.php` (12 اختبار)
- `docs/phase-ol-implementation-specification.md`

**مُعدَّل (Additive):**
- `App\Enums\AuditEvent` (+`OrganizationArchived`, `OrganizationRestored` — AD-001 مُعدَّل)
- `App\Policies\OrganizationPolicy` (+`archive`, `restore`)
- `App\Models\Organization` (+`status` بـFillable، +`isArchived()`)
- `OrganizationResource`/`EditOrganization`: **إزالة `DeleteAction` بالكامل** (Hard Delete أُلغي من الـDomain فعليًا)، إضافة أفعال أرشفة/استعادة

**AD-016/AD-001 مُسجَّلتان بـ`marketplace-architecture-blueprint.md` وimplementation-specification.md** (تفصيل بقسم منفصل بهذي الجلسة).

---

## 2. النتائج التقنية (كلها صحيحة، مؤكَّدة تجريبيًا)

| البند | النتيجة |
|---|---|
| #1 Archive بمؤسسة لديها Subscription | ✅ يُلغى صراحة عبر `OrganizationSubscriptionService::cancel()` |
| #2 Archive بمؤسسة لديها Seats | ✅ تُحرَّر تلقائيًا |
| #3 إبطال الوصول بعد Archive | ✅ عبر `EntitlementResolver`، **صفر تعديل عليه** |
| #4 لا إعادة وصول بعد Restore | ✅ الاشتراك يبقى `cancelled` |
| #5 Restore | ✅ `status` يعود `active` فقط |
| #6 Authorization (رفض Admin/Member) | ✅ عند استدعاء الـService مباشرة بفاعل حقيقي |
| #7 محاولة وصول لعضو سابق | ✅ مرفوضة |
| #8 لا Orphan Subscription | ✅ صفر Subscription نشط لمؤسسة مؤرشَفة |
| #9 Audit Events | ✅ `OrganizationArchived`/`Restored` بـ`organization_id`/`actor_user_id` صحيحين |
| #10 Concurrency | ✅ **دليل تجريبي حقيقي** (قسم 3 أدناه) |
| #11 Regression | ✅ 169/169 |

**اكتشاف جانبي مُوثَّق (لا إصلاح، خارج نطاق OL):** `SeatService::assign()` لا يتحقق من `subscription.status=active` — نظريًا يمكن تعيين مقعد لاشتراك ملغى بعد Archive. **لا خطر وصول فعلي** (`EntitlementResolver` يرفض بصرف النظر) — اختبار مخصَّص يوثّق هذا صراحة.

---

## 3. Concurrency — دليل تجريبي حقيقي

نفس منهجية Phase OI (قاعدة SQLite منفصلة تمامًا، محذوفة بالكامل بعدها): محاولتا Archive حقيقيتان متزامنتان (عمليتا OS منفصلتان) لنفس المؤسسة.

```
العملية 1: SUCCESS - archived
العملية 2: REJECTED - Illuminate\Database\DeadlockException - database is locked

النتيجة النهائية: status=archived (مرة واحدة)، subscription=cancelled (مرة واحدة)،
audit_logs(OrganizationArchived)=1 سجل واحد بالضبط — لا ازدواج، لا فساد بيانات.
```

**صفر أثر على قاعدة التطوير** — قاعدة الاختبار المؤقتة حُذفت بالكامل فور الانتهاء.

---

## 4. Regression

```
{"tool":"phpunit","result":"passed","tests":169,"passed":169,"assertions":443,"duration_ms":4180}
```
169/169 (157 قبل Phase OL + 12 جديدة)، صفر كسر.

---

## 5. تحقق بصري — الاكتشاف حدث هنا تحديدًا

رحلة كاملة عبر Filament الحي: قائمة المؤسسات (عمود "الحالة" الجديد يظهر صحيحًا) → تعديل مؤسسة → محاولة أرشفة **فشلت بخطأ Authorization** (الاكتشاف أعلاه) → **أُعيدت المحاولة بفاعل Owner حقيقي عبر Tinker مباشرة، نجحت فورًا** (يثبت المنطق سليم، المشكلة بالفاعل فقط) → استعادة ناجحة.

**صفر أخطاء Console/HTTP 500 طوال الرحلة** — الفشل كان رفضًا نظيفًا (`Notification` واضحة، لا صفحة عطل خام)، بالضبط كما صُمِّم.

---

## 6. بيانات اختبارية صناعية دائمة إضافية (شفافية كاملة، بنفس مبدأ الجلسات السابقة)

لإجراء التحقق البصري بأمان (بدل المساس بالمؤسسات الحقيقية الثلاث)، أُنشئت مؤسسة اختبارية جديدة + مستخدمان + اشتراك + مقعد — والآن **عالقة بشكل دائم بنفس آلية `Cascade Test Org` سابقًا** (لها `audit_logs` حقيقية، Hard Delete غير ممكن بالتصميم نفسه):

```
organizations.id=6   ("مؤسسة تحقق OL المؤقتة" — الآن status=archived، حالة نهائية نظيفة)
users.id=          ol-playwright-owner@example.com, ol-playwright-member@example.com
```

**لا أثر على المؤسسات الثلاث الحقيقية أو بياناتها** — فُحِصت وأُكِّدت `status=active` بلا أي تغيير بعد كل هذا التحقيق.

---

## الخلاصة

**منطق Phase OL (Service/DB/Audit/Concurrency) صحيح 100%، مُختبَر بعمق، بلا أي فجوة تقنية.** **لكن الميزة غير قابلة للاستخدام الفعلي عبر Filament اليوم لأي موظف حكم ورقم واقعي** — اكتشاف حقيقي أثناء التحقق البصري، يمس Phase OI المُعتمَدة أيضًا (Transfer Ownership). **لم أختر حلًا من نفسي** — ثلاثة خيارات مطروحة أعلاه، كل واحد له أثر أمني/معماري مختلف يستحق قرارك الصريح.

**متوقِّف الآن. لا إصلاح تلقائي، لا قرار مُتخَذ نيابة عنك. بانتظار توجيهك على خيارات القسم الأول قبل اعتبار Phase OL "قابلة للاستخدام" فعليًا — رغم إن الكود والاختبارات كلها صحيحة ومكتملة.**
