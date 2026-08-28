# Platform Authorization Foundation — مراجعة أمنية مستقلة #2 (بعد Hardening Pass)

**النطاق:** مراجعة عدائية جديدة وكاملة للكود بعد `docs/platform-authorization-hardening-completion-report.md` — **ليست إعادة قراءة لتقرير الإصلاح نفسه**. تتبّعت كل مسار من جديد، بما فيه مسارات لم تُلمَس بهذي الدفعة، بحثًا عن أي طريقة **تجميعية** (Chaining) لإعادة إنتاج نفس النتيجة المحظورة أصلًا حتى لو كل خطوة بمفردها أصبحت محمية. صفر تعديل كود أو قاعدة بيانات.

---

## الحكم النهائي

# 🔴 SECURITY REVIEW #2: BLOCKED

**السبب:** Finding H1 — **الثغرة المحورية اللي طلبتَ إغلاقها ("Staff يمنح نفسه Owner ← يُسحَب منه Staff ← يبقى Owner دائم") لا تزال قابلة للتنفيذ فعليًا اليوم، عبر مسارين مُنفصلين تمامًا (كل خطوة منهما مُصرَّح بها بمفردها اليوم)، بدل خطوة واحدة كما كانت قبل Hardening Pass.** تحققت منها عمليًا (تنفيذ فعلي، لا افتراض) — مرفق الدليل بقسم Finding H1.

**التقييم الإيجابي أولًا:** Hardening Pass أغلق فعليًا **كل ما استهدفه صراحة** — Finding #1 الأصلي (`CreateAction`) وثغرته الشقيقة المكتشَفة (`changeRole()`) مُغلقان بإحكام، مُثبَتان بـ19 اختبارًا جديدًا شاملة اختبارات Livewire حقيقية. Finding #2 (`SeatService`) مُغلق بإحكام أيضًا. **المشكلة ليست بجودة الإصلاح المُنفَّذ — المشكلة أن باب ثالث بنفس الغرفة بقي مفتوحًا، لم يكن ضمن نطاق Finding #1 الأصلي لأنه مسار مختلف تمامًا (`transferOwnership()`، لا `add()`/`changeRole()`).**

---

## ملخص الـFindings

| # | العنوان | الخطورة | Exploitable | مصدره |
|---|---|---|---|---|
| H1 | `transferOwnership()` يعيد فتح نفس الثغرة المحورية عبر سلسلة خطوتين مصرَّح بهما كلاهما | **Critical** | **فعليًا، مُتحقَّق منه بالتنفيذ** | مسار لم يُلمَس بـHardening Pass (خارج نطاق Finding #1/#2 الحرفي) |
| H2 | `authorizeGrantingOwnership()` — سباق نظري بين فحص "هل يوجد Owner" وقفل الصف الفعلي | Low | نظري (نافذة ضيقة جدًا، أثرها محدود) | أثناء تطبيق الإصلاح |
| H3 | Finding #4 الأصلي (`livewire/update` يتجاوز `canAccessPanel`) لا يزال قائمًا كما هو | Medium (غير مُتغيِّر) | ضيق (يتطلب جلسة Staff مفتوحة مسبقًا) | لم يُطلَب إصلاحه، مُعاد تأكيده فقط |
| H4 | `remove()` لا تزال تسمح لـAdmin (لا Owner فقط) بإزالة Owner (لو ليس الأخير) | Informational | نظري | سلوك Phase OI أصلي، غير مرتبط بهذي الدفعة، يُذكَر للأمانة فقط |

---

## Finding H1 — `transferOwnership()` يعيد فتح السيناريو المحوري (Critical)

### الدليل — تحقّق فعلي بالتنفيذ، لا افتراض

كتبت اختبارًا مستقلًا (نُفِّذ فعليًا ضد بيئة الاختبار المعزولة القياسية، ثم حُذف فور التحقق — لم يُترَك أي أثر بالكود أو بالمشروع، مطابقةً لقيد "ممنوع تعديل الكود" بهذي المراجعة):

```php
$realOwner = User::factory()->create(['is_platform_staff' => false]);
$organization = Organization::create(['name' => 'Managed Org', ...]);
$realOwnerMembership = Membership::create(['user_id' => $realOwner->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Owner]);

$staff = User::factory()->create(['is_platform_staff' => true]);

// الخطوة ١ — مسموحة اليوم بالكامل (manageMembers: Owner/Admin/Staff):
$staffMembership = app(MembershipService::class)->add($staff, $organization, $staff, MembershipRole::Admin);

// الخطوة ٢ — مسموحة اليوم بالكامل (transferOwnership: Owner/Staff، لم تُعدَّل بـHardening Pass):
app(MembershipService::class)->transferOwnership($staff, $realOwnerMembership, $staffMembership, MembershipRole::Admin);
```

**النتيجة الفعلية (مطبوعة من التنفيذ الحقيقي):**
```
STAFF ROLE AFTER CHAIN: owner
REAL OWNER ROLE AFTER CHAIN: admin
```

**Staff أصبح Owner فعليًا، المالك الحقيقي تحوَّل لـAdmin — بخطوتين، صفر رفض بأي منهما.**

### لماذا الإصلاح الحالي لم يمنع هذا

`app/Services/MembershipService.php:135-160` — `transferOwnership()` **لم تُعدَّل بـHardening Pass إطلاقًا** (لم تكن مذكورة بـFinding #1 الأصلي، ولا بتعليماتك الأخيرة — نطاقها كان `MembershipsRelationManager::CreateAction` و`SeatService` تحديدًا). لا تزال تستخدم:
```php
Gate::forUser($actor)->authorize('transferOwnership', $from->organization);  // سطر 149
```
**بلا** فحص `authorizeGrantingOwnership()` الجديد (المُطبَّق فقط على `add()` و`changeRole()`). بما إنه `transferOwnership()` **تفترض أصلًا وجود Owner حقيقي** (`$from` نفسه Membership بدور Owner) — شرط "لو المؤسسة بلا Owner، اسمح لـStaff" لا معنى له هنا أصلًا؛ **المؤسسة دائمًا لها Owner عند استدعاء هذا التابع بالتعريف** — يعني الفرع "المؤسسة بلا Owner" بـ`authorizeGrantingOwnership()` غير قابل للتطبيق هنا، والفرع الصحيح الوحيد المنطقي هو "الفاعل يجب يكون Owner حقيقي بها بالفعل" — **تمامًا نفس القاعدة المطبَّقة على `add()`/`changeRole()`، لم تُطبَّق هنا فقط.**

### Attack Path الكامل (من واقع حالة اليوم)

1. Staff (بلا أي Membership) يفتح أي مؤسسة **لها Owner حقيقي بالفعل**.
2. يضيف نفسه كعضو بدور غير-Owner (مثلًا Admin) — مسموح، عبر `CreateAction` المُصلَحة نفسها (تستدعي `add()` بشكل شرعي تمامًا، لا تجاوز لها).
3. يستخدم زر "نقل الملكية" (`transferOwnership` Action، موجود أصلًا بـ`MembershipsRelationManager.php:75-107` منذ Phase OI) على عضوية Owner الحقيقية، ويختار **نفسه** كـ"المالك الجديد".
4. **النتيجة:** Staff = Owner دائم، المالك الأصلي = Admin. سحب صلاحية Staff لاحقًا **لا يغيّر شيئًا** — نفس النتيجة المحورية اللي طلبتَ إغلاقها بالضبط.

### هل هذا Regression من Hardening Pass؟

**لا** — المسار موجود منذ Phase OI (`transferOwnership()` نفسها لم تتغيّر). **لكنه فشل مباشر في تحقيق الهدف المُعلَن لـHardening Pass**: التقرير (`platform-authorization-hardening-completion-report.md` §10، البند ١) **ذكر هذا التحديدًا كـ"ملاحظة، خطر من الدرجة الثانية، لم يُعالَج"** — المراجعة هذي ترفعه لأولوية **Critical** بعد التحقق العملي أنه ليس "درجة ثانية" بل **نفس الخطورة تمامًا** (خطوتان بدل خطوة واحدة، كلتاهما بلا أي حاجز إضافي).

### Exploitable فعليًا أم نظري؟

**فعليًا، مُثبَت بالتنفيذ الحقيقي أعلاه.** لا شروط خاصة غير كون الفاعل Staff (نفس الشرط المطلوب أصلًا للوصول لـ`/admin`).

### التوصية

تطبيق **نفس** `authorizeGrantingOwnership()` المُستخدَمة بـ`add()`/`changeRole()` على `transferOwnership()` أيضًا — لكن بما إنه المؤسسة هنا **دائمًا** لها Owner (بحكم التعريف)، القاعدة تُبسَّط لفرع واحد: **الفاعل يجب يكون Owner حقيقي بنفس المؤسسة، أو Platform Staff بلا اشتراط إضافي فقط إذا كان هذا صراحة قرارًا سياسيًا تريده** (راجع "سؤال مفتوح" أدناه — هذا بالتحديد نوع القرار اللي لا يجوز لي أتخذه من نفسي).

**سؤال مفتوح يلزم قرارك الصريح (لا أفترض إجابته):** هل تريد Platform Staff يحتفظ بصلاحية `transferOwnership()` على مؤسسة **لها Owner حقيقي بالفعل** إطلاقًا (لحالات دعم فني استثنائية — مثلًا Owner حقيقي غير قادر يوصل الدعم)، أم تريدها **محصورة بـOwner حقيقي فقط** بمجرد وجود Owner (بلا استثناء لـStaff، مطابقةً تمامًا لقاعدة `add()`/`changeRole()` الجديدة)؟ **التوصية الافتراضية (الأكثر أمانًا، أوصي بها ما لم تُقرِّر خلاف ذلك): الخيار الثاني — لا استثناء لـStaff على مؤسسة مُدارة فعليًا، بأي تابع، بلا استثناء واحد.**

### Security Test مقترح

```php
public function test_staff_cannot_reach_ownership_via_add_then_transfer_chain(): void
{
    [$realOwner, $organization] = $this->organizationWithOwner();
    $realOwnerMembership = Membership::where('user_id', $realOwner->id)->where('organization_id', $organization->id)->first();
    $staff = User::factory()->create(['is_platform_staff' => true]);

    $staffMembership = app(MembershipService::class)->add($staff, $organization, $staff, MembershipRole::Admin);

    $this->expectException(AuthorizationException::class); // يفشل اليوم — الاستدعاء ينجح فعليًا
    app(MembershipService::class)->transferOwnership($staff, $realOwnerMembership, $staffMembership, MembershipRole::Admin);
}
```

---

## Finding H2 — سباق نظري بـ`authorizeGrantingOwnership()` (Low)

`authorizeGrantingOwnership()` (`app/Services/MembershipService.php:175-197`) تفحص "هل توجد مؤسسة بلا Owner" **قبل** الدخول بأي `lockForUpdate()` (القفل يحدث لاحقًا داخل `DB::transaction()` بكل من `add()`/`changeRole()`). نظريًا: طلبان متزامنان من فاعلين Staff مختلفين على مؤسسة يتيمة قد يجتازا فحص "لا يوجد Owner" **كلاهما** قبل أن يُنشئ أيهما الصف الأول (Race Window ضيق جدًا، بين قراءة `exists()` وبدء القفل). **الأثر: على الأكثر Owner إضافي زائد بمؤسسة كانت يتيمة (كلا الفاعلين Staff مخوَّلَين شرعًا لهذا الفعل أصلًا) — ليس Bypass لصلاحية، فقط تكرار عملية مشروعة.** لا يستحق إصلاحًا عاجلًا، أذكره للدقة فقط (نفس فئة الملاحظات اللي وثّقناها سابقًا بسباقات Phase 2B/OI المشابهة، وكلها اتُّبِع فيها نفس النمط: قفل + رسالة "أعد المحاولة" لا صفحة عطل).

### Security Test مقترح
اختبار تزامن حقيقي (نفس منهجية L2/OI/OL — قاعدة SQLite منفصلة، عمليتان فعليتان) — غير عاجل، يمكن تأجيله لدفعة Concurrency مستقبلية إن رغبت.

---

## Finding H3 — إعادة تأكيد Finding #4 الأصلي (لا تغيير)

`vendor/livewire/livewire/src/Mechanisms/HandleRequests/HandleRequests.php:22-23` لا تزال `->middleware('web')` فقط. لم يُطلَب إصلاحه، ولم يتغيّر. **بعد إغلاق Finding #1 الأصلي، الأثر العملي لهذا كان سيصبح شبه معدوم — لكن Finding H1 (أعلاه) يعيد فتح نفس نوع الأثر** (فعل حساس بلا حاجز Domain كافٍ قابل للاستدعاء عبر جلسة قديمة) **بمسار مختلف** (`transferOwnership()` نفسها تملك Gate check، لذا H3 لا يزيد خطورة H1 — فقط يعني إنه حتى بعد إصلاح H1، جلسة Staff مفتوحة سابقًا قد تُنفِّذ الفعل الصحيح لحظة الطلب لا لحظة تحميل الصفحة، وهذا سلوك متوقَّع وآمن كما وثَّقناه بالمراجعة الأولى).

---

## Finding H4 — ملاحظة للأمانة، لا Finding جديد فعليًا (Informational)

`remove()` (`app/Services/MembershipService.php:114-133`) تسمح لـAdmin (لا Owner فقط) بإزالة عضوية Owner (لو ليس الأخير — `assertNotLastOwner` تحمي فقط من الحذف الكامل). هذا **سلوك Phase OI الأصلي، مُختبَر ومُعتمَد سابقًا بـ16 اختبارًا بـ`MembershipServiceTest.php`**، لم يتغيّر بهذي الدفعة ولا علاقة له بمسار "منح Owner" (الإزالة تُنقِص، لا تمنح). أذكره فقط لاكتماله ضمن مسح شامل لكل ما يمس Membership — **ليس توصية بإصلاح، ولا جزءًا من نطاق هذي المراجعة.**

---

## تأكيدات إيجابية — أُعيد فحصها فعليًا بعد Hardening Pass

1. **Finding #1 الأصلي (CreateAction) مُغلَق فعليًا** — تحققت من `MembershipsRelationManager.php` مباشرة: `CreateAction` مُلفَّة بـ`using()` تستدعي `MembershipService::add()`، لا `Membership::create()` مباشر بأي مكان بالملف.
2. **ثغرة `changeRole()` الشقيقة مُغلَقة فعليًا** — تحققت من السطر 82-86 مباشرة: فرع `authorizeGrantingOwnership()` مُطبَّق عند الترقية لـOwner تحديدًا.
3. **Finding #2 (SeatService) مُغلَق فعليًا** — `assign()`/`release()` كلاهما يستدعيان `Gate::forUser($actor)->authorize('manageSeats', ...)` قبل أي تعديل حالة، تحققت من الكود مباشرة.
4. **الـ19 اختبارًا الجديدة حقيقية، غير قائمة على Mocks** — راجعتها سطرًا سطرًا: استدعاءات مباشرة للخدمات + **اختبارات Livewire حقيقية فعليًا** (`Livewire::test()` مع `callTableAction`) تحديدًا للفجوة اللي فاتت الجولة الأولى. هذا يسدّ الـBlind Spot المذكور بالمراجعة الأولى (§10) — **لكنه لم يغطِّ `transferOwnership()`**، وهذا بالضبط سبب فوات Finding H1.
5. **213/213 اختبارًا يمرّون** (تحققت بتشغيل فعلي)، **صفر تعديل على قاعدة البيانات الحقيقية** (تحققت بمقارنة العدّادات قبل/بعد: `users=11, organizations=5, memberships=7, audit_logs=10` بلا تغيير).
6. **AD-012/AD-005/AD-013 لم تُخرَق بأي تعديل جديد** — كل كود جديد بهذي الدفعة يستعلم `Membership::query()` مباشرة، صفر استخدام لـ`ActiveOrganizationContext` أو `EntitlementResolver`.

---

## الخلاصة

Hardening Pass نفَّذ بدقة وإحكام كل ما طُلب منه صراحة — **الإخفاق ليس بجودة التنفيذ، بل بحدود نطاقه**: `transferOwnership()` كانت الباب الثالث بنفس الغرفة، لم تكن مذكورة بالطلب الأصلي، وبقيت مفتوحة. النتيجة العملية: **السيناريو المحوري الذي وصفته أنت بنفسك ("Staff → Owner → إلغاء Staff → يبقى Owner") لا يزال قابلًا للتنفيذ اليوم، بخطوتين بدل خطوة واحدة.**

# 🔴 SECURITY REVIEW #2: BLOCKED

**الإصلاح المُقترَح ضيق ومحدَّد** (تطبيق `authorizeGrantingOwnership()` نفسها على `transferOwnership()`، زائد سؤال سياسة واحد يلزم قرارك) — لا يتطلب إعادة تصميم. بانتظار مراجعتك لهذا التقرير و`platform-authorization-hardening-completion-report.md` معًا قبل أي خطوة تالية، كما طلبت.
