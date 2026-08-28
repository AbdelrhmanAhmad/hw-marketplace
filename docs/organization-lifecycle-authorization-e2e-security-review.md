# Organization Lifecycle + Authorization — Adversarial End-to-End Security Review

**النطاق:** تتبّع كامل للسلسلة `canAccessPanel → OrganizationPolicy → MembershipService → OrganizationLifecycleService → OrganizationSubscriptionService → SeatService → EntitlementResolver`. **لا اعتماد على القراءة/grep وحدهما** — تحقّق فعلي عبر PHPUnit مباشر، `Livewire::test()`، طلبات HTTP حقيقية، **ومتصفح Playwright حقيقي** ضد بيئة معزولة تمامًا عن Dev DB (نفس منهجية L2/OI/OL/Security Review #1-2، صفر أثر متبقٍّ). صفر تعديل كود أو قاعدة بيانات.

---

## الحكم النهائي

# 🔴 SECURITY REVIEW: BLOCKED

**السبب:** Finding E1 — **مؤسسة مؤرشَفة (`status=archived`) يقدر Staff (أو حتى Owner لو وصل للخدمة مباشرة) يُنشئ لها اشتراكًا مؤسسيًا جديدًا نشطًا، وهذا الاشتراك يمنح وصولًا حقيقيًا فعليًا لأعضائها عبر `EntitlementResolver`.** تحقَّق هذا بتنفيذ فعلي حقيقي (لا افتراض): مؤسسة أُرشِفت، اشتراك جديد أُنشئ لها، عضو حصل على مقعد، `EntitlementResolver::resolve()` أعاد **`ALLOWED`**. هذا يُبطِل الوعد المركزي لـArchive نفسه (نصّ التأكيد بالواجهة حرفيًا: *"يُلغي كل اشتراك مؤسسي نشط... ويُبطِل كل وصول — فورًا"*) — الأرشفة تمنع الوصول **الموجود وقت الأرشفة فقط**، لا أي وصول **جديد** يُنشأ لاحقًا لمؤسسة مؤرشَفة.

**التقييم الإيجابي:** كل ما بُني بمراحل Platform Authorization Foundation/Hardening/AD-017 صمد بالكامل — Authorization (من يقدر يفعل ماذا) سليم 100%. **المشكلة هذي المرة ليست Authorization، بل Domain State Enforcement**: النظام يسأل "هل هذا الفاعل مخوَّل؟" بشكل صحيح دائمًا، لكن لا يسأل أبدًا "هل هذي المؤسسة أصلًا في حالة تسمح بهذا الفعل؟" — فئة مختلفة تمامًا من الفجوة عن كل ما اكتُشف سابقًا.

---

## ملخص الـFindings

| # | العنوان | الخطورة | Exploitable | Regression؟ | يمنع الإغلاق؟ |
|---|---|---|---|---|---|
| E1 | مؤسسة مؤرشَفة يمكن منحها اشتراكًا **جديدًا نشطًا** → وصول حقيقي فعلي | **Critical** | فعليًا، مُتحقَّق منه بالتنفيذ الحقيقي | لا (فجوة أصلية، لم تُكتشَف من قبل) | **نعم** |
| E2 | `changeSeatLimit()`/`assign()` لا يتحققان من حالة الاشتراك (نشط/ملغى) | Medium | لا (مُخفَّف بالكامل بفلتر `EntitlementResolver`) | لا (**معروفة وموثَّقة مسبقًا** بكود Phase OL نفسه، أُعيد تأكيدها فقط) | لا |
| E3 | Owner حقيقي لا يقدر يصل لـ`/admin` إطلاقًا — فرع الـAuthorization الخاص به بالـPolicy غير قابل للتنفيذ عمليًا عبر الواجهة اليوم | Informational | — (ليس خطرًا، بل ملاحظة تصميم) | لا (نتيجة مباشرة ومقصودة لـPlatform Authorization Foundation) | لا |
| E4 | تسميات واجهة غير مُعرَّبة بالكامل (`اضافة membership`/`اضافة subscription`) | Low/Cosmetic | — | لا | لا |

---

## Finding E1 — Archive لا يمنع إنشاء اشتراك جديد (Critical)

### الدليل — تنفيذ فعلي حقيقي، لا افتراض

```php
// 1. مؤسسة حقيقية، Owner حقيقي، أُرشِفت فعليًا:
app(OrganizationLifecycleService::class)->archive($owner, $organization);
// النتيجة: $organization->status === 'archived' ✅ (مؤكَّد)

// 2. بعد الأرشفة مباشرة، نفس الفاعل (أو Staff) يُنشئ اشتراكًا لعنصر Marketplace لم يكن مشتركًا به:
$subscription = app(OrganizationSubscriptionService::class)->create($owner, $organization, $item, 'Professional', 5);
// النتيجة الفعلية: $subscription->status === 'active' ✅ (مؤكَّد — لا استثناء أو رفض)

// 3. عضو بالمؤسسة يحصل على مقعد بهذا الاشتراك الجديد:
app(SeatService::class)->assign($owner, $subscription, $member);

// 4. مصدر القرار الوحيد للوصول الفعلي — EntitlementResolver:
app(EntitlementResolver::class)->resolve($member, $item, $organization);
// النتيجة الفعلية: AccessDecision(allowed: true, reason: HasAccess) ← ALLOWED فعليًا
```

**السبب الجذري:** `app/Services/OrganizationSubscriptionService.php::create()` (السطر 34-77) يتحقق من `Gate::authorize('manageSubscription', ...)`، صلاحية العنصر (`billing_model`)، وعدد المقاعد — **لا يتحقق أبدًا من `$organization->isArchived()`**. لا يوجد أي حارس بالكود على الإطلاق يربط "حالة المؤسسة" بـ"إمكانية إنشاء اشتراك جديد لها".

### Attack Path

1. مؤسسة تُؤرشَف (بواسطة Owner أو Staff، فعل شرعي).
2. **بعد الأرشفة**، أي فاعل يملك `manageSubscription` على تلك المؤسسة (Owner الأصلي، أو أي Platform Staff) يُنشئ اشتراكًا **جديدًا** (عنصر Marketplace لم يكن مُشترَكًا به من قبل، أو نفس العنصر لو كان أُلغي واستُبدِل — الحالة الشائعة عمليًا: عضو يطلب "أنا مهتم" بعد الأرشفة، Staff يوافق ظنًّا إنه فعل إداري عادي، بلا أي تحذير من النظام).
3. عضو بالمؤسسة يحصل على مقعد بهذا الاشتراك الجديد.
4. **النتيجة:** وصول حقيقي فعلي لتطبيق Marketplace، **بينما المؤسسة مُصنَّفة "مؤرشَفة" بكل مكان آخر بالنظام** (القائمة، البادج، السجلات) — تناقض مباشر بين ما تعرضه الواجهة ("مؤرشَفة") وما يحدث فعليًا (وصول نشط).

### هل هذا Exploitable من فاعل غير مخوَّل؟ لا — لكن هذا ليس الخطر

الخطر هنا **ليس** تصعيد صلاحيات (Privilege Escalation) — الفاعل مخوَّل بالفعل (Owner أو Staff). الخطر هو **انهيار عزل الحالة (State Isolation)**: النظام كله (Filament UI، Badge، منطق Restore الذي يتعمَّد عدم إعادة التفعيل) مبني على افتراض "مؤسسة مؤرشَفة = بلا وصول نشط إطلاقًا حتى تُستعاد بوعي" — هذا الافتراض **غير محمي فعليًا بالكود**، فقط "صحيح بالصدفة" لأن لا أحد جرَّب إنشاء اشتراك جديد بعد الأرشفة من قبل.

### هل Regression من مرحلة سابقة؟

**لا** — فجوة أصلية موجودة منذ Phase OL نفسها (`create()` لم تتغيّر بهذا الجانب بأي مرحلة لاحقة). **لم تُكتشَف من قبل** لأن كل اختبارات Phase OL/Platform Authorization ركّزت على "هل الوصول **الموجود وقت الأرشفة** يُلغى؟" (نعم، صحيح ومؤكَّد) — لا أحد اختبر "هل يمكن إنشاء وصول **جديد** بعدها؟".

### التوصية

حارس واحد صريح في بداية `OrganizationSubscriptionService::create()`:
```php
if ($organization->isArchived()) {
    throw new InvalidArgumentException('لا يمكن إنشاء اشتراك جديد لمؤسسة مؤرشَفة.');
}
```
اختياري للاتساق الكامل (لا يمنع خطرًا فعليًا، Finding E2 يبقى آمنًا بفضل `EntitlementResolver`): نفس الحارس بـ`changeSeatLimit()`/`SeatService::assign()`.

### Security Test مقترح

```php
public function test_cannot_create_subscription_for_archived_organization(): void
{
    [$owner, $organization, $item] = $this->organizationOwner();
    app(OrganizationLifecycleService::class)->archive($owner, $organization);

    $this->expectException(InvalidArgumentException::class); // يفشل اليوم — الإنشاء ينجح فعليًا
    app(OrganizationSubscriptionService::class)->create($owner, $organization, $item, 'Professional', 5);
}
```

---

## Finding E2 — `changeSeatLimit()`/`assign()` لا يتحققان من حالة الاشتراك (Medium، معروفة مسبقًا)

**أُعيد تأكيدها فقط، لم تُكتشَف الآن.** موجودة ومُوثَّقة صراحة بكود المشروع نفسه: `tests/Feature/Organization/OrganizationLifecycleServiceTest.php:211-236`:
```php
/**
 * اكتشاف جانبي مُوثَّق (لا إصلاح — خارج نطاق OL، راجع المواصفة قسم 6):
 * SeatService::assign() لا يتحقق من subscription.status=active...
 * EntitlementResolver يبقى يرفض الوصول رغم ذلك... فلا خطر وصول فعلي.
 */
```

**تحققت بتنفيذ فعلي مستقل** (لا اعتماد على النص الموثَّق فقط): أنشأتُ اشتراكًا، أرشفتُ المؤسسة (فحُوِّل الاشتراك لـ`cancelled`)، رفعتُ `seat_limit` عبره، عيَّنتُ مقعدًا جديدًا — **نجح كلاهما بلا رفض** — لكن `EntitlementResolver::resolve()` أعاد **`DENIED`** (`reason=needs_subscription`) لأن الاستعلام يفلتر صراحة `where('status', 'active')`. **مؤكَّد: لا خطر وصول فعلي، فقط تناقض بيانات (اشتراك "ملغى" له مقعد "مُعيَّن") — Accepted Risk، لا تغيير موصى به الآن.**

---

## Finding E3 — Owner حقيقي لا يقدر يصل لـ`/admin` (Informational، لا خطر)

### الاكتشاف أثناء التحقق البصري

حاولتُ تسجيل دخول Owner حقيقي (غير Staff) عبر متصفح حقيقي لأرشفة مؤسسته بنفسه — **فشل الدخول** (نفس الرسالة العامة لخطأ بيانات الاعتماد، مطابقة تمامًا لسلوك Filament المُكتشَف بـSecurity Review #1). هذا **متوقَّع وصحيح 100%** حسب تصميم Platform Authorization Foundation (`canAccessPanel()` = Staff فقط) — **ليس خطأً**، لكنه يعني عمليًا:

> **اليوم، لا يوجد أي مستخدم حقيقي غير Platform Staff يقدر يصل لزر "أرشفة"/"استعادة" عبر أي واجهة حقيقية.** فرع "Owner" بـ`OrganizationPolicy::archive()`/`restore()` (`isPlatformStaff() || Membership.role=Owner`) **صحيح برمجيًا لكن غير قابل للتنفيذ عمليًا اليوم** إلا عبر استدعاء مباشر للخدمة (Tinker/API مستقبلي).

**لماذا هذا ليس Finding أمني:** الاتجاه آمن (تقييد الوصول، لا توسيعه) — هذا العكس التام لمشكلة Phase OL الأصلية (`admin@marefa.local` بلا Membership، فرع Staff كان غير قابل للتنفيذ). أذكره **كملاحظة معمارية/منتجية** تستحق انتباهك عند التخطيط لأي بوابة Self-Service للعملاء مستقبلًا (خارج نطاق هذي المراجعة تمامًا) — لا فعل مطلوب الآن.

---

## Finding E4 — تسميات غير مُعرَّبة بالكامل (Low/Cosmetic)

لاحظتُ أثناء التحقق البصري: زر إنشاء عضوية جديدة يظهر كـ"اضافة membership"، وزر إنشاء اشتراك "اضافة subscription" (مزيج عربي/إنجليزي، بدل "إضافة عضو"/"إضافة اشتراك"). **لا علاقة أمنية إطلاقًا** — لاحظته بالصدفة أثناء تصوير الشاشات، أذكره للأمانة فقط. لا يستحق أولوية.

---

## إجابات مباشرة على كل سؤال طرحتَه صراحة

| السؤال | الجواب | الدليل |
|---|---|---|
| هل Staff يستطيع Archive مؤسسة لا يملكها؟ | **نعم، بالتصميم** (Option D، مؤكَّد سابقًا) | `OrganizationPolicy::archive()` |
| هل Staff يستطيع Restore؟ | **نعم، بالتصميم** | نفس الآلية |
| هل Member يستطيع Archive؟ | **لا** | مُتحقَّق مباشرة + `Livewire::test()` |
| هل Admin يستطيع Archive؟ | **لا** | مُتحقَّق مباشرة + `Livewire::test()` |
| هل Owner يستطيع Archive؟ | **نعم على مستوى الـDomain، لكن لا يقدر يصل للواجهة إطلاقًا اليوم** (Finding E3) | — |
| هل Archive يلغي كل الـSubscriptions/Seats/Access الموجودة؟ | **نعم، بشكل صحيح تمامًا** | `test_1`/`test_2` بـ`OrganizationLifecycleServiceTest.php`، مُعاد تأكيدها |
| هل Restore يعيد المؤسسة فقط ولا يعيد Access تلقائيًا؟ | **صحيح، مؤكَّد** | `restore()` سطر 58-60، تعليق صريح بالكود |
| هل يمكن الوصول لـApp بعد Archive بأي مسار؟ | **الوصول الموجود وقت الأرشفة: لا. وصول جديد يُنشأ بعدها: نعم (Finding E1، الثغرة)** | — |
| هل يمكن إنشاء Subscription لمؤسسة Archived؟ | **نعم — Finding E1، الثغرة الحرجة** | تنفيذ فعلي مباشر |
| هل يمكن Assign Seat لمؤسسة Archived؟ | لاشتراك ملغى: نعم لكن بلا وصول حقيقي (E2). لاشتراك جديد بعد الأرشفة: نعم **مع وصول حقيقي** (E1) | — |
| هل يمكن تغيير Seat Limit لمؤسسة Archived؟ | نعم (E2)، بلا أثر وصول حقيقي | — |
| هل يمكن الالتفاف عبر Filament/Livewire/Service مباشرة؟ | **لا لأي Authorization** (كل الأبواب موحَّدة). **نعم لـFinding E1 تحديدًا** — لأنه ليس مشكلة Authorization أصلًا | — |
| هل Organization IDs يمكن تبديلها (IDOR)؟ | **لا** | كل Policy method يفحص `organization_id` صراحة (غير مُعدَّل، مؤكَّد سابقًا وهنا) |
| هل Active Organization Context يمكن استغلاله؟ | **لا** — `ActiveOrganizationContext::switchTo()` يفحص عضوية حقيقية، ولا يمنح أي صلاحية بذاته (AD-011)؛ لا علاقة له بحالة Archive | `app/Support/ActiveOrganizationContext.php` |
| هل هناك أي mutation لـMembership خارج الـService؟ | **لا — صفر** | Inventory كامل (نفس منهجية AD-017)، 3 نقاط فقط، كلها بـ`MembershipService` |
| هل هناك أي mutation لـSubscription خارج الـService؟ | **لا** | `grep` شامل، `OrganizationSubscriptionService` هي الكاتب الوحيد |
| هل هناك أي mutation لـSeat خارج الـService؟ | **لا** | `SeatService` هي الكاتبة الوحيدة |
| هل `owner_id` ما زال قادرًا على التأثير على Authorization؟ | **لا — صفر إشارة** | `grep -rn "owner_id" app/Policies app/Services` → لا نتائج |
| هل حذف/Archive المؤسسة يترك orphaned records؟ | **لا** — Archive لا يحذف أي شيء (Hard Delete غير موجود بالـDomain أصلًا) | — |
| هل Audit Trail يظل append-only بكل هذي المسارات؟ | **نعم** — الحماية (AD-001، Triggers + AppendOnlyBuilder) على مستوى الـModel/DB، غير مرتبطة بأي مسار استدعاء | لم يتغيّر شيء بهذا الجانب |

---

## منهجية التحقق الفعلية (لا Grep وحده)

| الطبقة | ما جرى فعليًا |
|---|---|
| **استدعاء مباشر (Service-level)** | تنفيذ حقيقي لـ`archive()`→`create()`→`assign()`→`EntitlementResolver::resolve()` بسلسلة كاملة، على قاعدة بيانات اختبار حقيقية — Finding E1/E2 اكتُشفا/تأكَّدا هنا |
| **`Livewire::test()`** | Archive/Restore عبر مكوّنات Filament حقيقية (`EditOrganization`) لأربعة فاعلين (Owner/Admin/Member/Staff) — 6 اختبارات، جميعها نجحت كما هو متوقَّع |
| **HTTP حقيقي** | طلب `GET /admin/organizations/{id}/edit` كـMember حقيقي → `403` مباشرة (لا حتى يصل لزر Archive) |
| **متصفح Playwright حقيقي** | بيئة معزولة تمامًا (SQLite منفصلة + `php artisan serve` مستقل + تسجيل دخول فعلي) — Staff أرشف مؤسسة فعليًا (لقطة شاشة قبل/بعد، الحالة تحوَّلت لـ"مؤرشَفة" بصريًا)، ثم فتح شاشة "إضافة اشتراك" لنفس المؤسسة المؤرشَفة ووجدها **مفتوحة وقابلة للتعبئة بلا أي تحذير** — دليل بصري إضافي يدعم Finding E1. حاولتُ أيضًا تسجيل دخول Owner حقيقي — **فشل** (Finding E3، متوقَّع وصحيح). البيئة والقاعدة حُذفتا بالكامل بعد الانتهاء — صفر أثر على Dev DB (تحققتُ: `organizations=5, subscriptions=6` قبل وبعد، بلا تغيير). |

---

## تأكيدات إيجابية (فحصتها فعليًا، لا افتراضًا)

1. **Archive تلغي كل وصول موجود وقتها بشكل صحيح 100%** — لا فجوة بهذا الجزء تحديدًا.
2. **Restore لا يعيد أي Access/Subscription تلقائيًا** — مؤكَّد بالكود والتعليق الصريح.
3. **Authorization لكل الأفعال الحساسة سليم بالكامل** — لا Bypass واحد وجدته عبر أي مسار (Filament/Livewire/Service مباشر/HTTP) — كل ما بُني بالمراحل السابقة صمد.
4. **لا Mutation خارج الـServices المخصَّصة** لـMembership أو Subscription أو Seat — Inventory كامل مؤكَّد.
5. **`owner_id` معزول تمامًا عن Authorization** — صفر إشارة بأي مكان.
6. **لا Orphaned Records** — Hard Delete غير موجود بالـDomain.
7. **AD-012/AD-005/AD-013 لم تُخرَق** — لا كود جديد يعتمد على Context بدل Membership، ولا تسريب بين Entitlement وAuthorization.

---

## الخلاصة

المشكلة هذي المرة **مختلفة جذريًا** عن كل ما اكتُشف سابقًا — ليست "من يقدر يفعل هذا؟" (Authorization، سليم بالكامل) بل **"هل يجوز فعل هذا أصلًا الآن، بصرف النظر عن هوية الفاعل؟"** (Domain State Enforcement، فجوة حقيقية). هذا يستحق فئة تحقق جديدة كليًا لأي عملية Lifecycle مستقبلية: **ليس فقط Authorization Matrix — بل Domain State Matrix أيضًا** ("هل هذا الفعل مسموح بالحالة الحالية للكيان؟").

# 🔴 SECURITY REVIEW: BLOCKED

**الإصلاح المقترَح ضيق جدًا** (حارس واحد بـ`create()`) — لا يتطلب أي إعادة تصميم. بانتظار قرارك.
