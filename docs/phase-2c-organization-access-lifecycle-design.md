# Phase 2C — Organization Access Lifecycle Design

**الحالة:** تحليل + تصميم فقط. **صفر كود، صفر Migration، صفر تعديل Controller/Model/Route/View/Database.** كل ما بهذي الوثيقة نتيجة قراءة فعلية للكود والقاعدة الحية وقت الكتابة — لا افتراض، لا اعتماد على الذاكرة أو الوثائق وحدها.
**المرجع:** `marketplace-architecture-blueprint.md` · `marketplace-implementation-specification.md` · `phase-2-organization-access-design.md` · `phase-2b-organization-subscription-access-design.md` · `phase-2b-completion-report.md` · `marketplace-access-control-audit.md` · `legacy-subscription-closure-plan.md` · `phase-l2-completion-report.md` · AD-001/005/006/007/012/013/014/015.
**منهجية الفحص:** قراءة مباشرة لكل من `SeatService`، `OrganizationSubscriptionService`، `OrganizationSeatController`، `OrganizationPolicy`، `SubscriptionSeat`/`Membership`/`Organization` Models، Filament RelationManagers، `routes/web.php`، ملفات الاختبار الفعلية (أسماء التوابع)، وقاعدة البيانات الحية (استعلام مباشر لكل مؤسسة وعضوية). **141/141 اختبار ناجح** كخط أساس قبل أي كتابة بهذي الوثيقة، صفر كود لُمِس أثناء الفحص.

---

## Executive Summary

### هل Phase 2C مطلوبة فعلًا؟

**بصياغتها الأصلية (AD-010: "Seat Management" كمرحلة منفصلة بعد 2B) — لا، لأنها مبنية بالفعل.** الاكتشاف الأهم بهذي الوثيقة (راجع CONFLICT-1 أدناه): وثيقة تصميم "Phase 2B" نفسها (`phase-2b-organization-subscription-access-design.md`, سطر 7) صرّحت بأنها تغطي **عمدًا وصراحة** ما سمّته AD-010 بـ"2B + 2C + 2D معًا" — لأن الثلاثة "لا يمكن تصميمها بمعزل صحيح عن بعضها". والتنفيذ الفعلي (`phase-2b-completion-report.md`) نفّذ هذا النطاق الموسَّع بالكامل: `SubscriptionSeat`، `SeatService` (assign/release/reassign)، Concurrency حقيقي مُختبَر، Tenant Isolation مُختبَر. **Seat Management موجود، يعمل، ومُختبَر — ليس فجوة.**

**بصياغة أدق: نعم، هناك عمل حقيقي متبقٍّ — لكنه ليس "بناء Seat Management من الصفر"، بل إغلاق فجوات محددة ودقيقة اكتُشفت أثناء هذا الفحص، أهمها فجوة واحدة حرجة فعليًا موجودة بالبيانات الحية الآن (CONFLICT-2).**

### ما الذي تم بناؤه بالفعل (مؤكَّد بالكود + الاختبارات، لا افتراضًا)

- دورة حياة Seat كاملة: `assign`/`release`/`reassign`/`releaseAllForUserInOrganization` — الأربعة موجودة بـ`SeatService`، الأربعة مُختبَرة (`SeatServiceTest`، 7 اختبارات، شامل Concurrency حقيقي).
- دورة حياة Organization Subscription: `create`/`changeSeatLimit`/`cancel` — الثلاثة بـ`OrganizationSubscriptionService`، مُختبَرة (`OrganizationSubscriptionServiceTest`، 6 اختبارات).
- `MembershipRevoked` → تحرير مقاعد تلقائي عند مغادرة عضو — مُختبَر (`MembershipRevokedSeatCleanupTest`).
- Tenant Isolation بتسع طبقات مفحوصة ومؤكَّدة سليمة (`marketplace-access-control-audit.md`).
- Concurrency حقيقي (لا محاكاة) على آخر مقعد متاح — منفَّذ ومُختبَر مرتين (Phase 2B الأصلي + إعادة تأكيد بجلسة L2 الأخيرة على مسار مختلف).
- Audit كامل (`SeatAssigned`/`SeatReleased`/`AccessGranted`/`AccessRevoked`/`SubscriptionCreated`/`SubscriptionActivated`/`SubscriptionCancelled`) مع `organization_id` صحيح بكل سجل مؤسسي.

### ما الذي ينقص فعليًا (مؤكَّد بالفحص، لا تخمينًا)

1. **🔴 حرج (CONFLICT-2):** `Organization.owner_id` حقل معروض بلوحة الإدارة **بلا أي علاقة فعلية بمن يملك صلاحية Owner الحقيقية** (`Membership.role=owner`). **مؤكَّد بالبيانات الحية:** 2 من 3 مؤسسات حقيقية اليوم بها `owner_id` يشير لمستخدم **لا يحمل دور Owner فعليًا** بتلك المؤسسة.
2. **🔴 حرج:** لا حماية بنيوية أو إجرائية تمنع إزالة/تخفيض **آخر عضو بدور Owner** بمؤسسة — يترك المؤسسة بلا أي شخص يقدر يدير اشتراكها (`manageSubscription` يتطلب Owner حصرًا).
3. **🟠 عالي:** إنشاء مؤسسة عبر Filament **لا يُنشئ عضوية Owner تلقائيًا** — يمكن إنشاء مؤسسة بـ`owner_id` معبَّأ، بصفر صفوف `Membership`، فتصبح غير قابلة للإدارة من اليوم الأول.
4. **🟠 عالي:** `MembershipsRelationManager` بلوحة الإدارة يستخدم Filament CRUD القياسي (`EditAction`/`DeleteAction`) — **تعديل/حذف مباشر على الـModel، بلا مرور بأي Domain Service وبلا أي قاعدة عمل**، بعكس `SubscriptionsRelationManager` (الذي يمر عبر `OrganizationSubscriptionService` بشكل صحيح).
5. **🟡 متوسط:** `OrganizationSubscriptionService::cancel()` **موجود ومُختبَر لكن بلا أي مسار استدعاء حقيقي** — لا Route، لا زر Filament. لا طريقة فعلية (حتى لموظف حكم ورقم) لإلغاء اشتراك مؤسسي اليوم.
6. **🟡 متوسط:** حالة `AccessAssignment.status=suspended` مصمَّمة (`phase-2b-organization-subscription-access-design.md` قسم E) لكن **صفر تطبيق** — لا Migration تدعمها فعليًا بمعنى استخدام حقيقي، لا Service، لا UI. **هذا ليس بالضرورة فجوة يجب إغلاقها الآن** (Future-ready ≠ Future-built) — يُذكَر للتوثيق فقط.
7. **🟢 منخفض:** `SeatService::reassign()` مُختبَر بمستوى Service لكن بلا مسار HTTP — قد يكون قرارًا مقصودًا (Release+Assign كافيان من الواجهة) أو فجوة، غير محسوم بأي وثيقة سابقة.

### ما الذي يجب تغييره

**لا شيء بهذي الوثيقة — تحليل فقط.** لو اعتُمد لاحقًا: أولوية إغلاق البندين 1-3 أعلاه (Owner Lifecycle Integrity) تفوق أي عمل آخر، لأنها الوحيدة اللي تمس بيانات حية موجودة فعليًا اليوم بحالة غير متسقة.

### ما المخاطر

مخاطر تشغيلية/بيانات (لا أمنية — لا تسرّب بين مؤسستين وُجِد، مؤكَّد بالتدقيق السابق ومُعاد التحقق جزئيًا هنا). التفصيل الكامل بقسم Security Review.

### ما الذي لا يجب لمسه

Header، Navigation، Dashboard، بوابة معرفة، Login/Authentication، أي تصميم عام — لا شيء بهذا التحليل يتطلب لمس أي منها (مؤكَّد، قسم "لا نلمس Core Platform" أدناه).

---

## CONFLICT-1 — تسمية المرحلة (AD-010 مقابل الواقع المُنفَّذ)

```
CONFLICT
Current Reality:      "Phase 2B" المُنفَّذة فعليًا (2026-08-10) غطّت عمليًا كامل نطاق
                       Seat Management (AD-010's "2C") و Organization Access (AD-010's "2D")
                       معًا — بتصريح واضح مسجَّل بمقدمة وثيقة تصميم 2B نفسها.
Documented Decision:  AD-010 ("توقيت CD-001") ينص على تسلسل خمس مراحل منفصلة:
                       2A → 2B → 2C → 2D → 2E، كل واحدة بانتظار اعتماد التي قبلها.
Risk:                 استخدام اسم "Phase 2C" الآن لعمل تم إنجاز معظمه فعليًا تحت اسم
                       "2B" يخلق التباسًا في تتبع الحالة عبر الوثائق — قد يُقرَأ لاحقًا
                       على إنه "لم يُبنَ شيء بعد" رغم وجود 104+ اختبار حقيقي.
Recommendation:        —  (بدون توصية ملزمة، القرار لك)
Decision Required:    هل نُعيد تسمية العمل المتبقي رسميًا (مثلًا "Organization Access
                       Hardening" بدل "Phase 2C")، أم نبقي اسم "Phase 2C" لكن نُعيد
                       تعريف نطاقه رسميًا بالوثائق ليقتصر على الفجوات المكتشَفة هنا فقط؟
```

---

## CONFLICT-2 — `Organization.owner_id` مقابل `Membership.role=Owner`

```
CONFLICT
Current Reality:      Organization.owner_id (عمود FK بسيط، Fillable، يُعبَّأ يدويًا عبر
                       Select بلوحة Filament) لا علاقة برمجية له بجدول Membership إطلاقًا.
                       OrganizationPolicy (المصدر الوحيد الفعلي للصلاحية الإدارية)
                       لا يقرأ owner_id إطلاقًا — يعتمد حصرًا على Membership.role.
                       مؤكَّد بفحص مباشر للبيانات الحية الآن:
                         org 1 "مكتب الرياض للمحاماة": owner_id=user6،
                           لكن دور user6 الفعلي بها = partner (لا owner)
                         org 2 "مكتب جدة الاستشاري": owner_id=user6،
                           لكن دور user6 الفعلي بها = lawyer (لا owner)
                         org 3 "مكتب الدمام للاستشارات المالية": owner_id=user6،
                           ودور user6 الفعلي بها = owner (الحالة الوحيدة المتّسقة)
                       أي 2 من 3 مؤسسات حقيقية اليوم بحالة غير متّسقة فعليًا، الآن،
                       بلا أي كود خاطئ تسبَّب بها — ببساطة لا آلية ربط موجودة أصلًا.
Documented Decision:  لا وثيقة سابقة (Blueprint/Phase 2/Phase 2B) ناقشت owner_id
                       كمفهوم مستقل عن Membership.role=Owner، ولا حسمت العلاقة بينهما.
                       الافتراض الضمني بكل وثائق Phase 2/2B إن "Owner" = دور Membership
                       فقط — owner_id لم يُذكَر ولو مرة بأي منها.
Risk:                 لوحة الإدارة (Filament) تعرض اسم "المالك" لموظفي حكم ورقم بناءً
                       على owner_id — قد يُضلِّل الموظف ليظن إن ذاك الشخص يتحكم فعليًا
                       باشتراك/مقاعد المؤسسة، بينما الصلاحية الفعلية لشخص آخر تمامًا
                       (أو لا أحد، لو لا Membership بدور owner إطلاقًا).
Recommendation:        —  (بدون توصية ملزمة، القرار لك)
Decision Required:    (أ) owner_id حقل عرض/تاريخي فقط، لا صلة له بالصلاحيات — يبقى
                       كما هو، ربما يُعاد تسميته بالواجهة ليكون أوضح ("جهة الاتصال
                       الأساسية" بدل "المالك")؟ أم (ب) يجب مزامنته تلقائيًا مع أول/
                       فقط Membership بدور Owner (يتطلب كودًا لاحقًا لو اعتُمد)؟
```

---

## Gap Analysis

| Area | Current State | Required State (لو اعتُمد إغلاقها) | Gap | Risk | Priority |
|---|---|---|---|---|---|
| Owner Integrity (`owner_id`) | غير مرتبط بـ`Membership.role` إطلاقًا، بيانات حية متضاربة فعليًا (2/3) | تعريف رسمي واحد لمصدر "من المالك" | CONFLICT-2 أعلاه | تضليل إداري، لا تسرّب بيانات | **Critical** |
| Zero-Owner Protection | لا فحص يمنع حذف/تخفيض آخر Owner بمؤسسة | قاعدة عمل صريحة تمنع هذا (على مستوى Service أو Policy) | لا حماية بنيوية إطلاقًا | مؤسسة تصبح غير قابلة للإدارة نهائيًا (يحتاج تدخل يدوي بقاعدة البيانات لإصلاحها) | **Critical** |
| Org Creation → Owner Membership | فعلان منفصلان يدويان بلوحتين مختلفتين (Organization ثم Membership) | ربط تلقائي أو تحذير واضح عند الإنشاء بلا Membership مرافقة | لا آلية ربط، لا تحذير | مؤسسة "يتيمة" فعليًا من لحظة الإنشاء لو نُسي الخطوة الثانية | **High** |
| Membership Role Change/Delete | Filament CRUD مباشر، بلا Service، بلا قاعدة عمل | يمر عبر Domain Service كـ`SubscriptionsRelationManager` (اتساق نمطي) | لا Service لإدارة Membership إطلاقًا اليوم | تغييرات صامتة بلا Audit Trail (لا حدث Audit لتغيير دور أصلًا اليوم) | **High** |
| Organization Subscription Cancellation UI | Service مُختبَر، صفر مسار استدعاء حقيقي (لا Route، لا زر Filament) | زر/Action إداري يستدعي `cancel()` الموجود فعلًا | فجوة تنفيذ بسيطة (الـService جاهز) | لا خطر بيانات — فقط قدرة تشغيلية ناقصة | **Medium** |
| `AccessAssignment.suspended` | مصمَّم بالوثيقة، صفر تطبيق فعلي | — (غير مطلوب الآن، Future-ready) | متعمَّد، موثَّق مسبقًا | لا خطر — لا حاجة منتجية مؤكَّدة اليوم | **Low (بالتصميم، ليس فجوة)** |
| `SeatService::reassign()` HTTP path | مُختبَر بمستوى Service، صفر Route/UI | — (قد يكون مقصودًا) | غير محسوم | لا خطر — Release+Assign يحققان نفس الأثر عبر الواجهة الحالية | **Low، Open Question** |
| Audit لتغيير الدور | لا حدث Audit لتغيير `Membership.role` إطلاقًا | — | غياب أثر تدقيقي لفعل حسّاس (تغيير دور Admin/Owner) | لا يمكن معرفة "من رقّى/خفّض من" لاحقًا | **Medium** |

---

## Domain Lifecycle Diagram

```
Organization ──(owner_id، عرضي فقط، غير مُنفَذ — CONFLICT-2)
     │
     ├──< Membership >── User
     │        │
     │        role: Owner/Admin/Partner/Lawyer/Accountant/FinancialConsultant/Trainee/Client
     │        │
     │        [حالة غير قانونية غير ممنوعة اليوم: صفر Membership بدور Owner]
     │
     └──< Subscription (subscriber_type=organization) ──> SubscriptionPlan (seat_limit)
              │                                                    │
              │                          [حالة غير قانونية غير ممنوعة: seat_limit < 1 مرفوض
              │                           بالإنشاء (validated)، لكن تخفيضه لاحقًا محمي فقط
              │                           بـchangeSeatLimit (BR-2B-08، مُطبَّق ومُختبَر ✅)]
              │
              └──< SubscriptionSeat >── User (نفس عضو Membership المستهدَف)
                        │
                        [حالة غير قانونية ممنوعة ✅: مقعد لمستخدم ليس عضوًا (SeatService::assign يتحقق)]
                        │
                        └──(1:1 عند assigned)──> AccessAssignment
                                                        │
                                                        [حالة نظرية غير ممنوعة بصراحة: Seat
                                                         released لكن Access يبقى active —
                                                         غير قابلة للحدوث عمليًا لأن release()
                                                         يُبطِل الاثنين بمعاملة واحدة ✅، لكن
                                                         لا قيد DB يمنعها بنيويًا لو استُدعي
                                                         كود مستقبلي خاطئ يتجاوز SeatService]
                                                        │
                                                        └──> EntitlementResolver (خطوة 5)
                                                                    │
                                                                    └──> Application Access
```

**حالات غير قانونية يجب ألا تسمح بها المنظومة (ملخَّص):**
| الحالة | ممنوعة اليوم؟ | الآلية |
|---|---|---|
| مقعد لمستخدم ليس عضوًا بالمؤسسة | ✅ ممنوعة | `SeatService::assign()` يتحقق من `Membership` مباشرة |
| تجاوز `seat_limit` | ✅ ممنوعة | `lockForUpdate` + عدّ صريح + `UNIQUE` DB |
| تخفيض `seat_limit` تحت العدد النشط | ✅ ممنوعة | `changeSeatLimit()` (BR-2B-08) |
| اشتراك مؤسسي بلا Owner ينشئه | ✅ ممنوعة | `OrganizationPolicy::manageSubscription` (لا Route بلا Policy) |
| **مؤسسة بلا أي Owner إطلاقًا** | ❌ **غير ممنوعة** | لا فحص — CONFLICT/Gap أعلاه |
| **`owner_id` لا يطابق أي Membership فعلي** | ❌ **غير ممنوعة** | لا ربط أصلًا — CONFLICT-2 |
| Seat موجود بلا AccessAssignment مرافق (بعد تعيين ناجح) | ✅ غير قابلة للحدوث عمليًا | `assign()` ينشئهما بنفس المعاملة |
| AccessAssignment نشط بلا Seat مرافق (مسار مؤسسي) | ✅ غير قابلة للحدوث عمليًا | نفس السبب أعلاه |

---

## Security Model

```
Authentication (Laravel Breeze، غير مُمَس)
     ↓
Active Organization Context (Phase 2A، Pointer فقط — AD-012)
     ↓
Membership Verification (استعلام DB مباشر عند كل فعل حسّاس، لا ثقة بالجلسة)
     ↓
Ownership / Scope (هل هذا الاشتراك/المقعد يخص المؤسسة المستهدَفة فعليًا؟
                    ensureSubscriptionBelongsToOrganization())
     ↓
Entitlement (EntitlementResolver — "يقدر يستخدم هذا التطبيق؟")
     ↓
Authorization (OrganizationPolicy — "يقدر يدير هذي المؤسسة؟"؛
                Application-level Authorization خارج نطاق Marketplace كليًا، AD-005)
     ↓
Action
```

**كل طبقة أعلاه مؤكَّدة بالكود الفعلي اليوم** (`OrganizationSeatController` يستدعي `$this->authorize()` ثم `ensureSubscriptionBelongsToOrganization()` صراحة بكل تابع، `SeatService::assign()` يعيد التحقق من عضوية الهدف بمعزل عن الفاعل) — **باستثناء طبقة واحدة غير موجودة إطلاقًا اليوم: لا فحص لسلامة/اكتمال Membership نفسها** (هل تبقى المؤسسة بـOwner واحد على الأقل بعد أي فعل؟) — هذي بالضبط الفجوة محل CONFLICT الثاني والـGap Analysis.

---

## Authorization Matrix

**مصدر كل قيمة: الكود الفعلي (`OrganizationPolicy`, Controllers, Filament RelationManagers) — لا تخمين. حيث لا يوجد تحقق فعلي مكتوب، السطر يُعلَّم `⚠️ غير محسوم بالكود`.**

| Action | Owner | Admin | Member (باقي الأدوار الستة) | Guest | Non-member |
|---|---|---|---|---|---|
| View Organization (Filament فقط، لا واجهة ذاتية) | Staff فقط | Staff فقط | Staff فقط | — | — |
| View Subscription (نفس الملاحظة) | Staff فقط | Staff فقط | Staff فقط | — | — |
| Create Subscription | ✅ (BR-2B-01، مُختبَر) | ❌ (مُختبَر صراحة: `test_admin_cannot_create_subscription_only_owner_can`) | ❌ | ❌ | ❌ |
| Change Plan / Seat Limit | ⚠️ **غير محسوم بالكود** — `changeSeatLimit()` بالخدمة **لا تستدعي أي Policy إطلاقًا**، فقط Filament `EditAction` القياسي يحميها ضمنيًا (لوحة إدارية داخلية أصلًا) | نفس أعلاه | ❌ | ❌ | ❌ |
| Assign Seat | ✅ (BR-2B-02، مُختبَر) | ✅ (نفس القاعدة) | ❌ (`manageSeats` يرفض) | ❌ | ❌ |
| Release Seat | ✅ | ✅ | ❌ | ❌ | ❌ |
| Reassign Seat | ⚠️ لا مسار HTTP يوجد أصلًا ليُختبَر تفويضه — Service فقط | نفس أعلاه | — | — | — |
| View Members (Filament فقط) | Staff فقط | Staff فقط | Staff فقط | — | — |
| Add Member | ⚠️ **غير محسوم بالكود** — Filament CRUD قياسي، لا Policy مخصَّصة | نفس أعلاه (لا تمييز Owner/Admin بهذا الفعل تحديدًا اليوم) | — | — | — |
| Remove Member | ⚠️ **نفس الملاحظة** — ولا حماية ضد إزالة آخر Owner (Gap الحرج) | نفس أعلاه | — | — | — |
| Open Application (استخدام فعلي) | حسب `EntitlementResolver` (مقعد فعّال) | نفس أعلاه | نفس أعلاه — أي دور يملك مقعدًا فعّالًا يقدر يفتح | ❌ (بلا مقعد) | ❌ |

**ملاحظة حاسمة:** الأسطر المعلَّمة `⚠️` ليست ثغرات أمنية بالمعنى الصارم — كل إدارة المؤسسات والاشتراكات والأعضاء اليوم **حصرية بلوحة Filament الداخلية لموظفي حكم ورقم فقط** (لا مسار ذاتي للمكتب/العميل نفسه لأي من هذي الأفعال، مؤكَّد بالكود — قرار مُوثَّق مسبقًا بوضوح لإنشاء الاشتراك تحديدًا بقسم Non-Goals لوثيقة 2B، لكن لم يُوثَّق صراحة لتغيير الخطة/إدارة الأعضاء). طالما الوصول محصور لموظفي حكم ورقم الموثوقين، غياب Policy دقيقة لكل فعل فرعي مخاطرة تشغيلية داخلية منخفضة — **لكنها تستحق قرارًا صريحًا لا فراغًا صامتًا**، تحديدًا لأن Filament بلا Policy طبقة تفويض حقيقية يعني "أي مستخدم Filament مصادَق = موثوق بالكامل" (نفس الفجوة الموروثة المذكورة بـ`marketplace-access-control-audit.md` قسم 3، لم تتغيّر، ليست جديدة بهذا التحليل).

---

## Membership Lifecycle — تحليل الحالات العشر المطلوبة

| # | الحالة | الحالة الحالية | الدليل |
|---|---|---|---|
| 1 | إنشاء عضو جديد | ✅ يعمل (Filament CRUD)، `UNIQUE(user_id, organization_id)` يمنع التكرار | Migration + `MembershipsRelationManager` |
| 2 | إزالة عضو | ✅ يعمل، يُطلِق `MembershipRevoked` (`booted()` hook)، يحرّر مقاعده بتلك المؤسسة فقط | `Membership::booted()` + `MembershipRevokedSeatCleanupTest` |
| 3 | تغيير Role | ⚠️ **يعمل تقنيًا (Filament EditAction)، بلا أي قاعدة عمل أو Audit** — لا فحص "هل هذا آخر Owner؟"، لا تسجيل من غيّر الدور ومتى | `MembershipsRelationManager` — CRUD قياسي بلا `->using()` مخصَّص |
| 4 | عضو بمؤسستين | ✅ يعمل، مُختبَر صراحة (`test_switching_between_two_organizations_shows_correct_isolated_access`) | `OrganizationAccessFlowTest` |
| 5 | Owner بمؤسسة و Member بأخرى | ✅ يعمل بنيويًا (لا قيد يمنعه)، غير مُختبَر بسيناريو مخصَّص لكن نفس آلية #4 تغطيه منطقيًا | استنتاج من التصميم، لا اختبار مباشر باسم هذا السيناريو تحديدًا |
| 6 | عضو يفقد عضويته أثناء امتلاكه Seat | ✅ يعمل، مُختبَر (`test_removing_membership_releases_seat_and_revokes_access_but_keeps_subscription`) | `MembershipRevokedSeatCleanupTest` |
| 7 | عضو يعود للمؤسسة بعد مغادرتها | ⚠️ **يعمل تقنيًا (Membership جديدة تُنشأ، لا قيد يمنع العودة)، لكن لا يستعيد مقعده القديم تلقائيًا** — يحتاج تعيين يدوي جديد. غير مُختبَر بسيناريو مخصَّص | لا اختبار مباشر — استنتاج من غياب أي منطق "استعادة" بـ`SeatService` |
| 8 | Owner يغادر المؤسسة | 🔴 **فجوة حرجة — لا حماية إطلاقًا.** لو كان آخر/الوحيد بدور Owner، تصبح المؤسسة بلا أي شخص يقدر ينشئ/يلغي اشتراكها (`manageSubscription` يتطلب Owner حصرًا) — لا خطأ يُعرَض، الحذف ينجح بصمت | لا فحص بالكود، لا اختبار — Gap مؤكَّد بقراءة `SeatService`/`OrganizationPolicy`/`MembershipsRelationManager` معًا |
| 9 | محاولة إدارة مؤسسة بدون Membership | ✅ ممنوعة، مُختبَرة | `test_seat_management_page_rejects_user_with_no_membership_in_target_organization` |
| 10 | استخدام Organization ID لا يخص المستخدم | ✅ ممنوعة، مُختبَرة (403 حقيقي) | `test_member_of_org_a_cannot_manage_seats_of_org_b_via_url_id_manipulation` |

---

## Seat Lifecycle — تحليل الحالات الأربع عشرة المطلوبة

| # | الحالة | الحالة الحالية | الدليل |
|---|---|---|---|
| 1 | Assign Seat | ✅ يعمل، مُختبَر | `test_assign_creates_seat_and_active_access` |
| 2 | Release Seat | ✅ يعمل، إبطال فوري (BR-2B-03) | `test_release_revokes_access_immediately` |
| 3 | Reassign Seat | ✅ يعمل بمستوى Service (Release+Assign بمعاملة واحدة)، **بلا مسار HTTP** | `test_reassign_moves_seat_from_one_user_to_another` |
| 4 | Seat limit | ✅ يُنفَّذ ويُختبَر | `test_cannot_exceed_seat_limit` |
| 5 | Last available seat | ✅ يُنفَّذ (Transaction+lockForUpdate)، مُختبَر منطقيًا + Concurrency حقيقي بجلسات سابقة | `test_cannot_exceed_seat_limit` + سجل Concurrency الحقيقي بتقرير 2B |
| 6 | Concurrent assignment | ✅ حقيقي (لا محاكاة)، مُختبَر عبر عمليتين CLI/HTTP فعليتين متزامنتين | `phase-2b-completion-report.md` §8 |
| 7 | Attempt to exceed seat limit | ✅ مرفوض صراحة، رسالة واضحة (لا 500) | `OrganizationSeatController::assign()` try/catch |
| 8 | Member leaves | ✅ يعمل (`MembershipRevoked`) | راجع Membership #6 أعلاه |
| 9 | Member role changes | ⚠️ **لا أثر على المقعد نفسه** (Seat/Access غير مرتبطين بـRole أصلًا بالتصميم — أي دور يقدر يحمل مقعدًا) — هذا **سلوك صحيح ومتعمَّد**، لا فجوة. لكن فقدان صلاحية *إدارة* المقاعد (لو كان الدور Admin/Owner وتغيّر) يعمل فورًا عبر Policy الحية، **غير مُختبَر بسيناريو مخصَّص لتغيير الدور نفسه** | استنتاج من التصميم (فصل Seat عن Role مقصود، AD-005) + غياب اختبار مباشر |
| 10 | Subscription cancellation | ✅ يعمل، يُبطِل كل المقاعد بمعاملة واحدة | `test_cancel_releases_all_seats_and_revokes_access` |
| 11 | Subscription reactivation | ⚠️ **لا مسار موجود إطلاقًا** — `OrganizationSubscriptionService` ليس فيها تابع `reactivate()`. لو أُلغي اشتراك مؤسسي، لا طريقة برمجية لإعادة تفعيله (فقط إنشاء اشتراك جديد لنفس العنصر — لكن `create()` تُرجِع السجل **الملغى** الموجود لو existing check لا يميّز الحالة! يستحق فحصًا دقيقًا) | `OrganizationSubscriptionService::create()` — الفحص `$existing = ...->first(); if ($existing) return $existing;` **لا يتحقق من الحالة (`status`)** — لو كان `cancelled`، يُعاد كما هو **بلا إعادة تفعيل ولا رفض** — نتيجة غامضة تستحق قرارًا صريحًا (راجع Open Questions) |
| 12 | Subscription plan change | ✅ مصمَّم (BR-2B-08، تبديل `subscription_plan_id`)، **لكن لا تنفيذ فعلي بالكود** — `SubscriptionsRelationManager` يعدّل `seat_limit` فقط على نفس الخطة، لا تبديل لخطة أخرى بالكامل | فحص مباشر لـ`SubscriptionsRelationManager::form()` — لا حقل لتبديل الخطة نفسها |
| 13 | Seat limit reduction | ✅ يعمل، محمي (BR-2B-08)، مُختبَر | `test_rejects_seat_limit_reduction_below_active_seat_count` |
| 14 | Seat limit increase | ✅ يعمل، مُختبَر | `test_can_increase_seat_limit` |

---

## Subscription Lifecycle (الحالات الموجودة فعليًا بالكود فقط)

```
created → active → cancelled
```

**هذا كل شيء — لا حالة رابعة موجودة بالكود.** `Subscription.status` (عمود `string` بسيط، لا Enum حتى) يحمل قيمتين فقط فعليًا بالممارسة: `active`/`cancelled` (مطابق تمامًا لمسار Legacy `app_subscriptions` ومسار Phase 1b الشخصي — نمط ثابت بالمشروع كامل). **لا Billing Engine، لا Stripe/Mada/Apple Pay، لا Renewal، لا Payment Retry، لا Invoice/Tax Engine — صفر منها موجود بالكود، ولن يُفترَض بهذي الوثيقة (Non-Goals ثابتة من كل وثائق Phase 2/2B السابقة).**

**نقطة غامضة مكتشَفة (راجع Seat Lifecycle #11 أعلاه):** إعادة إنشاء اشتراك لعنصر له اشتراك `cancelled` موجود مسبقًا **لا تُنشئ جديدًا ولا تُعيد التفعيل صراحة** — تُرجِع السجل الملغى كما هو. هذا سلوك غامض (لا موثَّق كقرار، لا مُختبَر) يستحق قرارًا — **تذكير بـAD-014 هنا مباشرةً:** أي حل مستقبلي لهذي النقطة يجب يحترم نفس مبدأ "لا إعادة تفعيل ضمنية بلا نية صريحة" المُطبَّق على الاشتراك الشخصي.

---

## AccessAssignment Lifecycle

| السؤال | الجواب (من الكود الفعلي) |
|---|---|
| متى يُنشأ؟ | لحظة `SeatService::assign()` الناجحة — بنفس معاملة إنشاء `SubscriptionSeat` |
| متى يصبح فعّالًا؟ | فورًا عند الإنشاء (`status=active`) — لا خطوة تفعيل منفصلة |
| متى يُسحب؟ | `SeatService::release()` (فوري) أو `MembershipRevoked` Listener أو `OrganizationSubscriptionService::cancel()` (جماعي) |
| ماذا يحدث عند Release Seat؟ | `AccessAssignment` المرافق → `revoked` بنفس المعاملة، فورًا (BR-2B-03) |
| ماذا يحدث عند مغادرة العضو؟ | نفس أعلاه، عبر `ReleaseSeatsOnMembershipRevoked` Listener |
| ماذا يحدث عند إلغاء Organization Subscription؟ | **كل** `AccessAssignment` المرتبطة (عبر كل المقاعد) → `revoked`، معاملة واحدة |
| ماذا يحدث عند إعادة تفعيل Subscription؟ | **غامض — راجع Subscription Lifecycle أعلاه**، لا مسار "إعادة تفعيل" حقيقي موجود |
| هل يمكن أن يكون هناك Seat بدون AccessAssignment؟ | **نظريًا نعم لو كود مستقبلي تجاوز `SeatService`** — لا قيد DB يمنع هذا بنيويًا (لا FK بينهما، فقط منطق التطبيق بـ`assign()`/`release()` يبقيهما متزامنين) |
| هل يمكن أن يكون هناك AccessAssignment بدون Seat (مسار مؤسسي)؟ | نفس الملاحظة أعلاه — ممكن نظريًا لو تجاوز الكود `SeatService` |
| ما الحالات غير القانونية الواجب منعها؟ | (أ) AccessAssignment مؤسسي نشط بلا Seat مرافق نشط — غير ممنوعة بقيد DB اليوم، فقط بانضباط الكود المرور عبر `SeatService` حصرًا. (ب) Seat نشط بلا Membership فعلية للمستخدم — **ممنوعة فعليًا** (`assign()` يتحقق مسبقًا) |

---

## Audit Requirements — مراجعة الأحداث المطلوبة

| الحدث | موجود؟ | ملاحظة |
|---|---|---|
| `SubscriptionCreated` | ✅ | مؤسسي وشخصي معًا |
| `SubscriptionCancelled` | ✅ | نفس أعلاه |
| `SubscriptionActivated` | ✅ | نفس أعلاه |
| `SeatAssigned` | ✅ | مؤسسي فقط (AD-009) |
| `SeatReleased` | ✅ | نفس أعلاه |
| `AccessGranted` | ✅ | كلا المسارين |
| `AccessRevoked` | ✅ | كلا المسارين |

**الأحداث الثمانية المغلقة بـAD-001 مكتملة بالكامل بمسارها الحالي — لا حدث ناقص من القائمة المعتمدة.**

**اقتراح واحد فقط، بسبب تشغيلي واضح (لا زيادة عشوائية):** لا يوجد أي حدث Audit لتغيير `Membership.role` أو إنشاء/حذف Membership نفسها — هذي أفعال حسّاسة (من يدير من) بلا أي أثر تدقيقي اليوم، بعكس كل فعل آخر بدورة الحياة الكاملة. **لا أُضيفه الآن (قرارك)** — أذكره لأن غيابه فجوة حقيقية بمنطق "من فعل ماذا" الذي بُني عليه AD-001 أصلًا، لا لمجرد إكمال القائمة.

---

## Security Review — الحالات الثمانية المطلوبة

| # | الهجوم | الحالة | الدليل |
|---|---|---|---|
| 1 | IDOR (`organization_id` بالـURL) | ✅ مرفوض | `test_member_of_org_a_cannot_manage_seats_of_org_b_via_url_id_manipulation` |
| 2 | Seat ID Manipulation | ✅ مرفوض | `ensureSubscriptionBelongsToOrganization()` + تحقق `Membership` بـ`SeatService` |
| 3 | Subscription ID Manipulation | ✅ مرفوض (نفس الآلية) | نفس أعلاه، مسار واحد لكل الموارد الفرعية |
| 4 | AccessAssignment Manipulation | ✅ لا مسار HTTP مباشر لتعديل `AccessAssignment` إطلاقًا (يُعدَّل فقط كأثر جانبي لـSeat) — لا سطح هجوم مباشر أصلًا | فحص شامل — لا Route يستهدف `AccessAssignment` مباشرة |
| 5 | Session Tampering (`active_organization_id`) | ✅ مرفوض | `test_tampering_active_organization_context_session_does_not_bypass_membership_check` |
| 6 | Role Manipulation (Member ينفّذ فعل Owner/Admin) | ✅ مرفوض لأفعال Seat/Subscription (`OrganizationPolicy`) — **⚠️ لكن لا حماية لو Member نفسه يستطيع الوصول للوحة Filament** (خارج نطاق ثقة Marketplace، فجوة موروثة معروفة مسبقًا لا جديدة) | `OrganizationPolicy` + ملاحظة `marketplace-access-control-audit.md` §3 |
| 7 | Race Conditions (`last available seat`) | ✅ محمي (حقيقي لا محاكاة) | `phase-2b-completion-report.md` §8 |
| 8 | Cross-tenant leakage (عضو بـA وB) | ✅ مرفوض | `test_switching_between_two_organizations_shows_correct_isolated_access` + `test_access_from_organization_a_does_not_leak_into_organization_b_context` |

**النتيجة: لا هجوم من الثمانية له مسار نجاح اليوم — مطابق تمامًا لخلاصة `marketplace-access-control-audit.md`، أُعيد التحقق جزئيًا هنا بقراءة الكود مباشرة لا بالاعتماد على تلك الوثيقة وحدها.**

---

## Tenant Isolation — إعادة تأكيد (لا استعلام جديد وُجِد يخالف القاعدة)

**القاعدة المؤكَّدة:** `session('active_organization_id')` مؤشِّر فقط (AD-012) — كل استعلام Seat/Subscription/Access بالكود الحالي يمرّر `organization_id`/`subscriber_id` من **معامل الطلب صراحة** (Route Model Binding)، لا من السياق النشط وحده. **فُحِص هذا من جديد بقراءة `OrganizationSeatController`, `SeatService`, `OrganizationSubscriptionService` بالكامل الآن — لا استثناء وُجِد.**

---

## Testing Strategy — Test Matrix لأي عمل مستقبلي (لو اعتُمد إغلاق الفجوات)

| الفئة | الاختبارات المطلوبة (لو نُفِّذ إغلاق الفجوات) |
|---|---|
| **Unit (Domain Rules)** | منع حذف/تخفيض آخر Owner · قاعدة إعادة تفعيل اشتراك مؤسسي ملغى (بعد حسم الغموض) |
| **Feature (HTTP/Policies)** | أي Route/Action جديد لو أُضيف (مثلًا زر Cancel بـFilament) |
| **Integration** | Membership Role Change + أثره على قدرة إدارة المقاعد لحظيًا (Policy يعيد التقييم فورًا) |
| **Security** | محاولة حذف آخر Owner عبر Filament مباشرة (لو أُضيف Policy) |
| **Concurrency** | لا حاجة جديدة — Seat Race مُغطّاة بالكامل فعليًا |
| **Regression** | **إلزامي لأي تغيير مستقبلي:** كامل 141 اختبار حالي + تأكيد صريح لكل من: Login, Dashboard الحالي, بوابة معرفة, Laws, Calculators, Bookmarks, Filament, Marketplace, My Apps, Personal Subscription, Organization Subscription — **لا شيء بهذي الوثيقة يقترح تغيير أي منها** |

---

## لا نلمس Core Platform (تأكيد صريح)

**فُحِص: هل أي فجوة مكتشَفة أعلاه تتطلب لمس Header/Navigation/Dashboard/بوابة معرفة/Login/الهوية العامة؟**

**الجواب: لا، إطلاقًا.** كل الفجوات الحرجة (Owner Integrity) والمتوسطة (Cancellation UI, Role Audit) محصورة بالكامل داخل: `app/Services/*` (Organization*)، `app/Filament/Resources/OrganizationResource/*`، وربما `app/Policies/OrganizationPolicy.php`. **صفر ملف بأي من Header/Navigation/Dashboard/بوابة معرفة يحتاج لمسًا لإغلاق أي فجوة مذكورة هنا** — لو ظهر لاحقًا أي شيء يبدو يحتاج ذلك، **لن يُنفَّذ**، يُسجَّل كـDependency منفصلة (طبقًا لتعليماتك، لا استثناء).

---

## Migration / Legacy — تأكيد عدم إعادة الفتح

**لا لمس لـ`app_subscriptions`، لا `FreeAppProvisioner`، لا أي منطق Legacy Provisioning.** L1 وL2 منتهيتان (`phase-l2-completion-report.md`) — كل ما بهذي الوثيقة يخص طبقة Organization الجديدة فقط (`subscriptions`/`subscription_seats`/`access_assignments` بمسار `subscriber_type=organization`)، بلا أي تقاطع مع الجدول القديم إطلاقًا.

---

## Open Questions — مرتَّبة حسب الأولوية

### Critical
1. **CONFLICT-1** — هل نُعيد تسمية العمل المتبقي (بدل "Phase 2C" الذي أُنجز معظمه فعليًا)، أم نُعيد تعريف نطاق "2C" رسميًا؟
2. **CONFLICT-2** — `owner_id`: حقل عرضي فقط أم يحتاج مزامنة تلقائية مع `Membership.role=Owner`؟ (البيانات الحية متضاربة الآن، 2/3 مؤسسات)
3. **آخر Owner:** هل نمنع حذف/تخفيض دور آخر Owner ببنية جديدة (Policy/Service check)، أم نقبل هذا كمخاطرة تشغيلية داخلية مُدارة يدويًا (طالما الوصول لـFilament محصور بموظفين موثوقين)؟

### High
4. **Org Creation بلا Owner Membership:** هل نربط إنشاء المؤسسة بإنشاء Membership تلقائيًا، أم نكتفي بتحذير Filament واضح؟
5. **Membership Role Change/Delete بلا Domain Service:** هل يستحق `MembershipService` مخصَّص (يطابق نمط `OrganizationSubscriptionService`)، أم يبقى Filament CRUD مباشرًا مقبولًا لأداة داخلية؟
6. **Reactivate Subscription الغامض:** هل `create()` على عنصر بسجل `cancelled` موجود يجب: (أ) يرفض صراحة (يتطلب فعل "إعادة تفعيل" منفصل واعٍ)، أم (ب) ينشئ سجلًا جديدًا (سجل ثانٍ لنفس المؤسسة/العنصر)، أم (ج) تابع `reactivate()` مخصَّص صريح؟ **تذكير: أي خيار يجب يحترم روح AD-014.**

### Medium
7. **Cancellation UI مفقودة:** هل تكفي إضافة زر/Action بسيط بـFilament يستدعي `cancel()` الموجود، أم يحتاج تصميمًا إضافيًا؟
8. **Audit لتغيير الدور:** هل يستحق حدثًا جديدًا (تاسع، يكسر القائمة المغلقة الحالية بـAD-001)، أم يُكتفى بالاعتماد على `updated_at`/سجلات Filament العامة (لو وُجدت)؟
9. **Subscription Plan Change الكامل (لا `seat_limit` فقط):** هل مطلوب فعليًا اليوم، أم يبقى مؤجَّلًا (لا حاجة منتجية مؤكَّدة)؟

### Low
10. **`SeatService::reassign()` بلا مسار HTTP:** إضافة زر/Route مخصَّص، أم يبقى Release+Assign كافيَين من الواجهة؟
11. **`AccessAssignment.suspended`:** يبقى غير مُستخدَم (كما هو، Future-ready) — لا حاجة لقرار الآن، مذكور للتوثيق فقط.

---

# Phase 2C Design Complete — No Code Written

---

## ملخص تنفيذي للاعتماد

**السؤال الأهم اللي يحتاج قرارك:** هذا التحليل يقلب افتراض "Phase 2C = ابنِ Seat Management" — لأنه **مبني بالفعل ويعمل** (104+ اختبار، Concurrency حقيقي، Tenant Isolation مؤكَّد). الاكتشاف الحقيقي بدلًا من ذلك: **فجوة حرجة واحدة موجودة الآن بالبيانات الحية** (`owner_id` لا يطابق `Membership.role=Owner` فعليًا بـ2 من 3 مؤسسات) **+ غياب حماية بنيوية ضد ترك مؤسسة بلا Owner إطلاقًا** — وهاتان تحديدًا الفجوتان اللي تستحقان قرارك أولًا، لا "بناء 2C" بالمعنى التقليدي.

**لا كود كُتب. لا Header/Dashboard/Navigation لُمِس. لا L2/Legacy أُعيد فتحه.**

**أطلب اعتمادك على:**
1. الإطار العام لهذا التحليل (هل التصنيف صحيح؟ هل فاتني شيء؟)
2. الأولوية للفجوتين الحرجتين (Owner Integrity) قبل أي عمل آخر
3. توجيهك على الأسئلة المفتوحة أعلاه (Critical تحديدًا) — لا حاجة لحسمها كلها الآن، لكن حتى معرفة أيها يستحق نقاشًا أعمق يساعد بتحديد الخطوة التالية

**بانتظار قرارك قبل أي بدء تنفيذ.**
