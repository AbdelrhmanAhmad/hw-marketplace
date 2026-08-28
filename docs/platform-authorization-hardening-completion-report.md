# Platform Authorization Foundation — Security Hardening Pass — تقرير الإكمال

**السياق:** استجابة لـ`docs/platform-authorization-security-review.md` (النتيجة: **BLOCKED**) و**اعتمادك** لتنفيذ Hardening Pass محدود، لا إعادة تصميم. هذا التقرير يوثّق كل إجراء اتُّخذ، ثم يُتبَع بمراجعة أمنية مستقلة ثانية (`docs/platform-authorization-security-review-2.md`) — **لا اعتماد تلقائي لنتيجتها، بانتظار مراجعتك.**

---

## 1. المبدأ الحاكم — إجابة السؤال الصريح الذي طرحته

> "من يملك حق إنشاء Membership أصلًا؟ ومن يملك حق إنشاء Owner Membership؟"

**الإجابة المُطبَّقة (بالكود، لا فقط توثيقًا):**

| الفعل | من يملك الحق | الآلية |
|---|---|---|
| إنشاء/تعديل Membership بدور **غير Owner** | Owner، Admin، أو Platform Staff | `manageMembers` (بلا تغيير عن Phase OI) |
| **منح دور Owner** (إنشاءً جديدًا أو ترقية عضو موجود) لمؤسسة **بلا Owner فعلي إطلاقًا** | Owner (نظريًا غير منطقي هنا) أو **Platform Staff** | `transferOwnership` — نفس منطق تأسيس أول Owner بمؤسسة يتيمة (Attack #5 بالمراجعة الأولى) |
| **منح دور Owner** لمؤسسة **لها Owner فعلي بالفعل** | **Owner حقيقي بتلك المؤسسة تحديدًا فقط** | فحص مباشر: الفاعل يجب يكون Membership.role=Owner **بنفس المؤسسة** — Platform Staff بمفرده **لا يكفي هنا** |

**لماذا هذا التفريق تحديدًا (لا حظر Staff الكامل، ولا سماح مطلق):**
- **حظر Staff الكامل من منح Owner** كان سيكسر Attack #5 (مؤسسة يتيمة بلا Owner — بالضبط الحالة التي بُني Option D لحلها، واعتمدتها بالمراجعة الأولى).
- **سماح مطلق لـStaff بمنح Owner دائمًا** هو بالضبط Finding #1 (الثغرة المكتشَفة).
- الفارق الحاسم: **هل توجد سلطة بشرية حقيقية على هذي المؤسسة بالفعل؟** لو لا — Staff يتصرف كإجراء إداري استثنائي (نفس منطق "مؤسسة بلا Owner، Staff يديرها مؤقتًا"). لو نعم — أي توسيع لدائرة الملكية يجب يمرّ عبر **مالك حقيقي حالي**، لا عبر صلاحية إدارية عامة قابلة للسحب.

هذا يُطبَّق عبر تابع مركزي واحد جديد `MembershipService::authorizeGrantingOwnership()` (`app/Services/MembershipService.php`)، مُستخدَم بمكانين: إنشاء Membership بدور Owner، **و**ترقية Membership موجودة إلى Owner عبر `changeRole()` — راجع قسم 3 لسبب الحاجة للمكان الثاني.

---

## 2. Finding #1 — الإصلاح

**الملف:** `app/Filament/Resources/OrganizationResource/RelationManagers/MembershipsRelationManager.php`

`CreateAction` أصبحت مُلفَّة بـ`->using()` تستدعي `MembershipService::add()` حصرًا — **لا `Membership::create()` مباشر بعد الآن من أي مسار Filament**. أي `AuthorizationException`/`InvalidArgumentException` تُترجَم لإشعار Filament واضح (نفس نمط باقي أفعال نفس الملف).

**الملف:** `app/Services/MembershipService.php` — تابع جديد `add(User $actor, Organization $organization, User $target, MembershipRole $role): Membership`:
- يفحص Authorization حسب الجدول أعلاه (قسم 1) **قبل** أي كتابة.
- يرفض عضوية مكرَّرة بوضوح (`InvalidArgumentException`، بدل ترك القيد الفريد بقاعدة البيانات يرمي استثناءً خامًا).
- يسجّل `AuditLog` (حدث `MembershipCreated` جديد) — **كل إنشاء Membership مُدقَّق الآن، شاملًا الدور الممنوح.**

---

## 3. اكتشاف إضافي أثناء الإصلاح — ثغرة شقيقة بـ`changeRole()`

أثناء تطبيق قاعدة "من يملك حق منح Owner"، لاحظت أن `MembershipService::changeRole()` (الموجودة أصلًا منذ Phase OI، **لم تكن جزءًا من Finding #1** الأصلي) كانت تسمح بترقية **أي** عضو موجود إلى `role=Owner` باستخدام `manageMembers` فقط (Owner/Admin/**Staff**) — **بلا نفس القيد** المُطبَّق الآن على الإنشاء المباشر. هذا كان يعني: Staff يقدر يتفادى القيد الجديد بالكامل — بدل إنشاء Membership جديدة بدور Owner (محظور الآن لو المؤسسة مُدارة)، يُنشئ عضوية عادية (`manageMembers` يسمح) ثم يرقّيها لـOwner عبر `changeRole()` (كانت **بلا** نفس القيد).

**تم إصلاحه بنفس الدفعة** (لم يكن سيصح إغلاق Finding #1 جزئيًا وترك هذا المسار مفتوحًا) — `changeRole()` تستخدم الآن نفس `authorizeGrantingOwnership()` عند الترقية إلى Owner تحديدًا، مع تسجيل حدث Audit جديد `OwnershipGranted` (مميَّز عن `MembershipCreated` لأنه ترقية لا إنشاء).

**هذا الاكتشاف بالذات يوضّح بالضبط لماذا رفضت اقتراح "امنع Staff من إنشاء Owner" كحل كافٍ منذ البداية — نفس الثغرة كانت لتعود خلال أسبوع عبر Action مختلف، تمامًا كما حذّرتَ.**

---

## 4. Finding #2 — الإصلاح

**الملف:** `app/Services/SeatService.php`:
- `assign(User $actor, ...)` — يضيف `Gate::forUser($actor)->authorize('manageSeats', $organization)` بعد التحقق البنيوي من نوع المشترِك (لا يمكن نقله قبل ذلك — `$subscription->subscriber` قد لا يكون Organization أصلًا، والـPolicy تتوقّع Organization تحديدًا).
- `release(User $actor, ...)` — نفس الإضافة، قبل أي تعديل حالة (أعيد ترتيب الكود ليحسب `$organization` أولًا).
- **`releaseAllForUserInOrganization()` عمدًا لم تُضَف لها نفس الفحص** — هذي نتيجة نظامية لحدث `MembershipRevoked` الذي يُطلَق فقط بعد `MembershipService::remove()` تحقق من Authorization أصلًا؛ الفاعل بحالات Fallback (بلا جلسة HTTP) هو العضو المُزال نفسه (موثَّق بـ`ReleaseSeatsOnMembershipRevoked`) — وهو لن يجتاز `manageSeats` أبدًا (تحديدًا لأنه أُزيل تَوًّا)، فإضافة الفحص هنا **تكسر التنظيف النظامي بلا أي فائدة أمنية حقيقية** (الفعل نفسه — تحرير مقاعد **العضو المُزال تحديدًا** — آمن بطبيعته بصرف النظر عن مُستدعيه). استُخرِج المنطق الفعلي لتابع خاص `performRelease()` يستدعيه كل من `release()` (بعد التحقق) و`releaseAllForUserInOrganization()` (مباشرة، بلا تحقق إضافي) — القرار موثَّق بتعليق بأعلى الملف.
- **لم يلزم أي تغيير بالتوقيع** — الأربعة توابع العامة كانت أصلًا تأخذ `$actor` كمعامل أول؛ الفجوة كانت غياب استخدامه للتحقق، لا غياب المعامل نفسه.

---

## 5. Finding #3 — أُعيد فحصه، لا تعديل كود (Not Applicable)

بحثت فعليًا (لا افتراضًا) هل يمكن نقل `Gate::authorize()` ليكون **أول سطر مطلق** بـ`changeSeatLimit()`/`cancel()` (مطابقةً لـ`create()`). **لا يمكن بأمان**: `$subscription->subscriber` علاقة Polymorphic (قد تكون `Organization` أو `User` لاشتراك شخصي)؛ فحص `subscriber_type` يجب يسبق أي استخدام لـ`$subscription->subscriber` كـ`Organization` بالـPolicy. تحققت أيضًا من الاتجاه الآخر (تمرير `User` instance لـ`Gate::authorize('manageSubscription', ...)`): بعكس Filament's الخاص (default-allow لو الـPolicy Method غير موجود)، **`Illuminate\Support\Facades\Gate` المُستخدَم مباشرة بكل خدماتنا يرفض افتراضيًا** لو لم يوجد Policy مطابق للنوع المُمرَّر — فلا خطر "سماح خاطئ"، فقط رفض مُربِك محتمَل لسيناريو غير موجود عمليًا اليوم (لا مسار حالي يمرّر اشتراكًا شخصيًا لهذي التوابع). **لا تعديل — إعادة الترتيب لن تُحسِّن أمانًا حقيقيًا، وتخاطر بكسر افتراض النوع.**

---

## 6. ملخص إعادة تقييم كل Findings الثمانية

| # | العنوان | الحالة | ملاحظة |
|---|---|---|---|
| 1 | Membership CreateAction bypass | ✅ **Fixed** | + ثغرة شقيقة بـ`changeRole()` اكتُشِفت وأُصلِحت بنفس الدفعة (قسم 3) |
| 2 | SeatService بلا Authorization داخلي | ✅ **Fixed** | `releaseAllForUserInOrganization()` استثناء مُبرَّر وموثَّق، لا فجوة متبقية بالمسارات المُستخدَمة من فاعل بشري |
| 3 | ترتيب الفحوصات بـOrganizationSubscriptionService | ✅ **Not Applicable** | أُعيد الفحص فعليًا، لا تعديل — التبرير موثَّق بقسم 5 |
| 4 | livewire/update يتجاوز canAccessPanel | ⚠️ **Accepted Risk (Remains Open، خارج النطاق)** | سلوك Livewire/Filament بنيوي، لا يُصلَح بتغيير محلي؛ الأثر العملي الوحيد (Finding #1) أُغلِق، ما تبقّى منخفض الحساسية بالتصميم (Catalog Resources) |
| 5 | إنشاء Membership غير مُدقَّق | ✅ **Fixed** | حدثا Audit جديدان: `MembershipCreated`، `OwnershipGranted` |
| 6 | Filament Password Reset Enumeration | ✅ **No Action Needed (سبق تقييمه: ليس خطرًا)** | لم يتغيّر، مُعاد تأكيده فقط |
| 7 | AppSubscriptionResource (Legacy) | ⚪ **Not Applicable لهذي المرحلة** | خارج نطاق Organization/Platform Staff Authorization كليًا |
| 8 | Organization.status نظريًا Fillable | ⚪ **Accepted Risk (نظري، لا تعرّض حالي)** | لا فعل مطلوب — لا Form يعرّضه اليوم |

**لا Finding من نوع Authorization Bypass/Domain Service بلا Authorization متبقٍّ بلا معالجة أو قرار صريح موثَّق.**

---

## 7. الملفات المتغيّرة

| الملف | التغيير |
|---|---|
| `app/Enums/AuditEvent.php` | إضافة `MembershipCreated`، `OwnershipGranted` |
| `app/Services/MembershipService.php` | تابع جديد `add()`؛ `changeRole()` مُعدَّل (قيد الترقية لـOwner + Audit)؛ تابع خاص جديد `authorizeGrantingOwnership()` |
| `app/Services/SeatService.php` | `assign()`/`release()` تتحقق داخليًا الآن؛ تابع خاص جديد `performRelease()` |
| `app/Filament/Resources/OrganizationResource/RelationManagers/MembershipsRelationManager.php` | `CreateAction` مُلفَّة بـ`using()` → `MembershipService::add()` |
| `tests/Feature/Platform/PlatformAuthorizationHardeningTest.php` | **جديد** — 19 اختبارًا |

**لا Migration جديدة، لا تعديل Schema.** التغيير الوحيد على قاعدة البيانات الحقيقية: **صفر** — لم تُشغَّل أي Migration، `is_platform_staff` كانت موجودة أصلًا من المرحلة السابقة.

---

## 8. الاختبارات الجديدة (19 اختبارًا، `PlatformAuthorizationHardeningTest.php`)

مطابقة للعشرة المطلوبة صراحة + سيناريو الـAttack الأهم:

| # | الاختبار | يثبت |
|---|---|---|
| 1 | `test_1_staff_can_bootstrap_first_owner_...` | Staff يؤسس أول Owner لمؤسسة يتيمة — مسموح، مُدقَّق |
| 2 | `test_2_customer_cannot_create_membership_directly` | Customer مرفوض |
| 3 | `test_3_member_cannot_create_membership_in_organization_...` | Member خارج نطاقه مرفوض |
| 4, 4b, 4c | `test_4*` | Staff **لا يقدر** يمنح نفسه أو طرفًا آخر Owner بمؤسسة مُدارة فعليًا — لا عبر `add()` ولا عبر `changeRole()` |
| 5 | `test_5_rejected_owner_grant_attempt_leaves_no_membership_row_...` | الرفض يمنع الإنشاء أصلًا — لا شيء "يُكتشَف" لاحقًا |
| 6 | `test_6_direct_membership_service_invocation_...` | استدعاء مباشر بمعزل عن Filament مرفوض لفاعل غير مخوَّل |
| 7, 7b, 7c | `test_7*` | `SeatService` مباشرة (assign/release) مرفوضة لغير المخوَّل؛ التنظيف النظامي يعمل |
| 8, 8b, 8c | `test_8*` | **Livewire حقيقي** (`Livewire::test()`) — نفس الفجوة الأصلية (Finding #1) مُختبَرة الآن عبر الطبقة اللي فاتت الجولة الأولى؛ فاعل مخوَّل ينجح فعليًا |
| 9, 9a, 9b | `test_9*` | Owner/Admin المصرَّح لهما يستمران بالعمل؛ الترقية لـOwner عبر تدقَّق |
| 10 | `test_10_cannot_create_duplicate_membership_...` | لا Regression على القيد الفريد |
| E2E | `test_e2e_platform_staff_cannot_bootstrap_permanent_ownership_...` | **السيناريو المحوري بالكامل**: الرفض يحدث *أثناء* المحاولة، لا بعد سحب Staff |

---

## 9. نتائج الاختبار الكامل

| | قبل Hardening | بعد Hardening |
|---|---|---|
| عدد الاختبارات | 194 | **213** |
| Assertions | 491 | 534 |
| النتيجة | 194/194 ✅ | **213/213 ✅** |

**صفر Regression.**

---

## 10. اكتشافات/ملاحظات جديدة تستحق انتباهك (لم أتخذ قرارًا فيها من نفسي)

1. **`MembershipService::transferOwnership()` (Phase OI، غير مُعدَّلة بهذي الدفعة) تسمح لـStaff بنقل ملكية موجودة فعليًا** (Owner-or-Staff، دون اشتراط "الفاعل Owner حقيقي" الجديد المُطبَّق على `add()`/`changeRole()`). الفارق: `transferOwnership()` تتطلب أصلًا وجود Membership مسبقة لكل من `$from` و`$to` — فلا يقدر Staff يستخدمها لمنح نفسه Owner **من الصفر** (يحتاج عضوية موجودة أصلًا كـ`$to`، والحصول عليها يمر الآن عبر `add()` المُقيَّدة). خطر من الدرجة الثانية، **لم يُعالَج بهذي الدفعة** (خارج ما طلبتَه صراحة: Finding #1 وFinding #2 فقط) — أتركه لتقييمك، وستتم مراجعته مجددًا بالمراجعة الأمنية الثانية (قسم التالي).
2. **الفارق بين "منح Owner لنفسه" و"منح Owner لطرف آخر"**: طبّقت نفس القاعدة على الحالتين (لا فارق فني بينهما بالكود — كلاهما يتطلب Owner حقيقي حالي لو المؤسسة مُدارة). لو تريد قيدًا إضافيًا مختلفًا خصيصًا لحالة "منح النفس" (مثلًا حظرها كليًا حتى لو بمؤسسة يتيمة)، هذا قرار سياسة إضافي لم أتخذه من نفسي — الاختبار `test_4c` يثبت أن منح طرف آخر مرفوض بنفس صرامة منح النفس اليوم.

---

## الخطوة التالية

تنفيذ المراجعة الأمنية المستقلة الثانية جارٍ الآن (`docs/platform-authorization-security-review-2.md`) — **لا اعتماد تلقائي لأي نتيجة PASS**، بانتظار مراجعتك الشخصية لهذا التقرير أولًا، كما طلبت.
