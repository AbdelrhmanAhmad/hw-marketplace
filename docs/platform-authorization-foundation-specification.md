# Platform Authorization Foundation / Security Boundary — Implementation Specification

**الحالة:** مواصفة تنفيذ فقط. **صفر كود، صفر Migration، صفر تعديل قاعدة بيانات.** التنفيذ الفعلي مرحلة منفصلة، بانتظار اعتمادك على هذي الوثيقة أولًا.
**الاتجاه المعماري المعتمَد:** Option D (`platform-administration-authorization-design.md`) — مسار صلاحية ثنائي (Organization Role **أو** Platform Staff)، لا استبدال لأحدهما بالآخر.
**النطاق:** إغلاق الحد الفاصل الأمني لـ`/admin` + إصلاح فجوة `OrganizationSubscriptionService` فقط. **لا Phase OL، لا Header/Dashboard/Navigation/Marketplace UI، لا `owner_id`، لا إصلاح Org 1/Org 2.**

---

## 1. الهدف الأول: إغلاق `/admin` بحقيقة Backend، لا واجهة

### 1.1 — الآلية

```php
// app/Models/User.php — implements Filament\Models\Contracts\FilamentUser

public function canAccessPanel(Panel $panel): bool
{
    return $this->is_platform_staff;
}
```

**لماذا هذا "Backend enforcement حقيقي" لا مجرد إخفاء واجهة:** `canAccessPanel()` تُستدعى بواسطة `Filament\Http\Middleware\Authenticate`/منطق الـPanel نفسه **قبل** عرض أي صفحة أو معالجة أي طلب — طلب HTTP مباشر لأي مسار `/admin/*` من مستخدم `is_platform_staff=false` **يُرفَض على مستوى الـMiddleware**، بصرف النظر عن أي شيء بالواجهة (لا زر يظهر أو يختفي، الرفض يحدث قبل توليد أي HTML). **هذا يغلق تحديدًا الفجوة الموصوفة بقسم 1.2/1.3 من `platform-administration-authorization-design.md`.**

### 1.2 — الحقل: أبسط شكل قابل للتوسع

**القرار (نهائي لهذي المرحلة، لا مفتوح):** عمود `boolean` واحد — `users.is_platform_staff`, `default(false)`.

**لماذا Boolean لا Enum/جدول أدوار منفصل:**
- لا حاجة فعلية اليوم لأكثر من "موظف/ليس موظف" — لا طلب لأدوار داخلية متعددة (Support/Finance/SuperAdmin)، **لا نبنيها بلا حاجة مؤكَّدة** (نفس مبدأ Future-ready ≠ Future-built المتكرر بكل وثيقة سابقة).
- **قابل للتوسع بلا كسر:** الواجهة البرمجية المُستهلَكة بكل مكان آخر بالكود هي `$user->isPlatformStaff(): bool` (Accessor/Method، لا القراءة المباشرة للعمود) — لو احتجنا لاحقًا تمييزًا أدق (مثلًا `role` Enum بقيم متعددة)، `isPlatformStaff()` تتغيّر داخليًا (`return $this->role === StaffRole::Staff`) **بلا أي تعديل على أي Policy أو Service يستهلكها اليوم.**

### 1.3 — Bootstrap أول Staff — القرار الأهم أمنيًا بهذي الوثيقة

**المبدأ الحاكم:** منح أول صلاحية Staff **لا يجوز يحدث عبر أي مسار HTTP/Filament إطلاقًا** — لأن ذلك دائري بالتعريف (تحتاج تكون Staff أصلًا لتصل لواجهة تمنح صلاحية Staff). **يجب يحدث عبر قناة خارج حد Filament الأمني بالكامل — الوصول لخادم التشغيل نفسه (CLI).**

**الآلية المصمَّمة: أمر Artisan مخصَّص، CLI فقط:**
```
php artisan platform:grant-staff {email}
php artisan platform:grant-staff {email} --revoke
```

**سلوكه المصمَّم:**
1. يبحث عن مستخدم بالبريد المُعطى — **لا ينشئ مستخدمًا جديدًا** (لو غير موجود، رفض واضح — يمنع الغموض "منح صلاحية لبريد قد يُسجَّل لاحقًا بواسطة شخص آخر").
2. يطلب تأكيدًا صريحًا (`$this->confirm()`) قبل التنفيذ، إلا بعلم `--force` (للاستخدام غير التفاعلي بسكربتات نشر مستقبلية، لو احتيج).
3. يُحدِّث `is_platform_staff` مباشرة.
4. يسجّل بـLaravel Log القياسي (`Log::channel('...')->info(...)`) — **ليس `AuditLog`**: هذا فعل بمستوى ثقة الخادم نفسه (من يملك وصول CLI أصلًا يملك تحكمًا كاملًا بالنظام بغض النظر) — لا يحتاج نفس صرامة `AuditLog` (المصمَّمة لأفعال HTTP-reachable بواسطة مستخدمين متعددي المستويات). **لو بُنيت مستقبلًا واجهة Filament لمنح/سحب صلاحية Staff لمستخدم آخر (توسّع طبيعي لاحق، غير مطلوب الآن) — تلك تحديدًا يجب تسجَّل بـ`AuditLog` لأنها HTTP-reachable.**

**تطبيق على البيئة الحالية (دون تنفيذ الآن):** لا Staff موجود اليوم إطلاقًا (`is_platform_staff` سيُنشأ بـ`default(false)` على كل الصفوف الحالية شاملة `admin@marefa.local`). **أول تشغيل فعلي للأمر أعلاه بعد اعتماد التنفيذ — قرارك، لا افتراضًا مني** (المرشَّح المنطقي الوحيد اليوم هو `admin@marefa.local`، بما إنه الحساب المُستخدَم بكل تحقق إداري بالمشروع، **لكن هذا ملاحظة لا قرار**).

**منح Staff إضافي بعد الأول:** **يبقى عبر نفس الأمر CLI بهذي المرحلة** — لا نبني واجهة Filament لإدارة Staff الآن (لا `UserResource` موجود أصلًا اليوم، بناء واحد الآن نطاق إضافي غير مطلوب صراحة). توسّع طبيعي لاحق لو احتيج فعليًا.

---

## 2. إصلاح `OrganizationSubscriptionService` — الفجوة الأخطر المكتشَفة

### 2.1 — الحالة الحالية (تأكيد)

`create()`, `changeSeatLimit()`, `cancel()` — **صفر `Gate::authorize()` داخلي بأي منها اليوم.**

### 2.2 — الإصلاح المصمَّم

كل تابع يضيف سطرًا واحدًا، بنفس نمط `MembershipService`/`OrganizationLifecycleService` (Phase OI/OL) **حرفيًا**:

```php
public function create(User $actor, Organization $organization, MarketplaceItem $item, string $planName, int $seatLimit): Subscription
{
    Gate::forUser($actor)->authorize('manageSubscription', $organization);
    // ... بقية المنطق، بلا تغيير
}

public function changeSeatLimit(User $actor, Subscription $subscription, int $newLimit): void
{
    Gate::forUser($actor)->authorize('manageSubscription', $subscription->subscriber);
    // ... بقية المنطق، بلا تغيير
}

public function cancel(User $actor, Subscription $subscription): void
{
    Gate::forUser($actor)->authorize('manageSubscription', $subscription->subscriber);
    // ... بقية المنطق، بلا تغيير
}
```

**تغيير توقيع ملحوظ:** `changeSeatLimit()` و`cancel()` **لا تملكان معامل `$actor` اليوم إطلاقًا** (فحص الكود الحالي: `changeSeatLimit(Subscription $subscription, int $newLimit)`, `cancel(User $actor, Subscription $subscription)` — الأخيرة تملك `$actor` فعلًا لكن بلا استخدامه للتفويض، فقط للـAudit Log). **هذا يعني `changeSeatLimit()` تحديدًا يحتاج تعديل توقيعها** (إضافة `$actor` كمعامل أول) — **تغيير Breaking على المستدعي الوحيد الحالي** (`SubscriptionsRelationManager::EditAction`) — يُذكَر هنا صراحة كأثر تنفيذي حقيقي، لا يُنفَّذ الآن.

**السبب لاستخدام نفس Ability (`manageSubscription`) بدل واحدة جديدة:** الفعل الثلاثة (إنشاء/تعديل حد المقاعد/إلغاء) **كلها بنفس مستوى الخطورة** (تحكّم كامل بوجود اشتراك المؤسسة) — لا حاجة لتمييز أدق اليوم (لا طلب له، Future-ready ≠ Future-built).

### 2.3 — `OrganizationPolicy::manageSubscription` — التوسيع (Option D مُطبَّق)

```php
public function manageSubscription(User $user, Organization $organization): bool
{
    return $user->isPlatformStaff()
        || Membership::query()
            ->where('user_id', $user->id)
            ->where('organization_id', $organization->id)
            ->where('role', MembershipRole::Owner)
            ->exists();
}
```

**نفس النمط يُطبَّق حرفيًا على باقي توابع `OrganizationPolicy` الموجودة فعليًا** (`manageSeats`, `manageMembers`, `transferOwnership`, `archive`, `restore` — الأخيرتان من Phase OL، لم تُعتمَد للتنفيذ بعد لكن التصميم يشملهما استباقًا لاتساق كامل يوم تُعتمَد):

```
كل تابع: (شرط Organization Role الأصلي كما هو تمامًا) OR $user->isPlatformStaff()
```

**لا `Gate::before()` — كل تابع يضيف الشرط صراحة بمعزل** (تفصيل السبب بقسم 5).

---

## 3. Platform Staff ≠ Organization Owner/Member — القيد الحاسم

**Staff لا يُدرَج بجدول `memberships` إطلاقًا، ولا يُحتسَب ضمن أي عدّ Owner/Member:**

- **Last Owner Rule** (`MembershipService::assertNotLastOwner`) تبقى تعدّ **`Membership.role=Owner` فقط** — Staff لا "يُصلِح" نقص Owner حقيقي بمجرد وجوده، فقط يقدر يتصرف نيابة عن المؤسسة إداريًا (Archive مثلًا) **بلا أن يصبح Owner مُسجَّلًا**.
- **`EntitlementResolver`** لا تتأثر إطلاقًا (لا تعديل عليها بهذي المرحلة ولا أي مرحلة سابقة) — Staff لا يكتسب "وصول Marketplace" تلقائيًا لأي مؤسسة بمجرد كونه Staff (مسألتان منفصلتان تمامًا: **صلاحية الإدارة** [هذي الوثيقة] مقابل **الوصول الاستخدامي لعنصر Marketplace** [`EntitlementResolver`، AD-013]).
- **`SubscriptionSeat`/`AccessAssignment`** لا يُنشآن لـStaff تلقائيًا بأي فعل إداري يقوم به — Staff يدير **بنيويًا**، لا "يستخدم" المؤسسة كعضو.

**الاختبار الحاسم لهذا القيد (قسم 7):** Staff يؤرشف مؤسسة، ثم يُتحقَّق إنه **لا يظهر بأي استعلام `Membership.role=Owner`** لتلك المؤسسة بعد الفعل.

---

## 4. Authorization Matrix الكاملة

**فصل صريح — Platform-level (من يدخل `/admin` أصلًا) مقابل Organization-level (وش يقدر يفعل بمؤسسة معيّنة بعد الدخول):**

### 4.1 — Platform-level

| الفاعل | `canAccessPanel()` |
|---|---|
| Platform Staff (`is_platform_staff=true`) | ✅ |
| Customer (أي مستخدم آخر، شاملة Owner/Admin/Member بأي مؤسسة) | ❌ |
| زائر غير مسجَّل | ❌ (أصلًا يفشل بـ`Authenticate` قبل حتى الوصول لـ`canAccessPanel`) |

**حاسم:** كون المستخدم Owner/Admin بمؤسسة حقيقية **لا يمنحه دخولًا لـ`/admin` بمفرده** — الدخول لـFilament مشروط بـPlatform Staff حصرًا، بمعزل تام عن أي دور مؤسسي. لو أراد Owner مستقبلًا واجهة ذاتية، تلك تكون خارج `/admin` بالكامل (خارج نطاق هذي الوثيقة).

### 4.2 — Organization-level (بعد اجتياز 4.1، أو نظريًا لواجهة ذاتية مستقبلية)

| Action | Customer (غير عضو) | Member | Admin | Owner | Platform Staff |
|---|---|---|---|---|---|
| Create Organization Subscription | ❌ | ❌ | ❌ | ✅ | ✅ |
| Change Seat Limit | ❌ | ❌ | ❌ | ✅ | ✅ |
| Cancel Organization Subscription | ❌ | ❌ | ❌ | ✅ | ✅ |
| Assign/Release Seat | ❌ | ❌ | ✅ | ✅ | ✅ |
| Change Member Role / Remove Member | ❌ | ❌ | ✅ | ✅ | ✅ |
| Transfer Ownership | ❌ | ❌ | ❌ | ✅ (Owner المصدر) | ✅ |
| Archive / Restore Organization | ❌ | ❌ | ❌ | ✅ | ✅ |
| Hard Delete Organization | ❌ | ❌ | ❌ | ❌ | **❌ — لا مسار له إطلاقًا بالـDomain (Phase OL)، حتى Staff** |
| إدارة كتالوج/محتوى المنصة (الموارد الثمانية غير المرتبطة بمؤسسة) | ❌ (لا وصول `/admin`) | ❌ | ❌ | ❌ | ✅ (عبر 4.1 فقط، لا تمييز إضافي) |

---

## 5. لماذا لا `Gate::before()` — قرار مصمَّم، لا افتراض

**`Gate::before()` كان سيبدو أبسط** (سطر واحد: "لو Staff، اسمح بكل شيء دائمًا") — **مرفوض هنا صراحة للأسباب التالية:**

1. **لا يوجد إثبات فعلي إنه مطلوب.** كل الأفعال الحساسة اليوم محصورة بـ`OrganizationPolicy` (6 توابع) — إضافة الشرط صراحة بكل تابع (قسم 2.3) **نفس الجهد تقريبًا** بلا المخاطرة العمياء لـ`before()`.
2. **`Gate::before()` يمنح صلاحية لأي Ability تُسجَّل مستقبلًا تلقائيًا، بلا مراجعة واعية.** لو أُضيفت لاحقًا Policy جديدة لسياق حسّاس (مثلًا Billing حقيقي يومًا ما) — `before()` يمنح Staff صلاحية عليها **فورًا وبصمت**، بلا قرار صريح وقتها. الشرط الصريح بكل تابع يجبر أي إضافة مستقبلية على **قرار واعٍ**: "هل Staff يقدر يفعل هذا أيضًا؟" — لا افتراض تلقائي.
3. **يطابق مبدأ ثابت بكل هذا المشروع:** لا حل عام يُخفي قرارات فردية (نفس روح رفض الحلول الشاملة اللي ظهرت بمناسبات سابقة — AD-014 رفض "حل نقطي عام" لصالح قاعدة دقيقة، AD-001 Hardening رفض حماية جزئية لصالح طبقات صريحة محدَّدة).

---

## 6. Filament Actions ↔ Domain Services — القاعدة اللي تمنع Authorization Gap

**القاعدة الملزمة (تُطبَّق على كل Filament Action مستقبلي يمس Domain حسّاس، لا استثناء):**

> **Filament لا يتخذ قرار تفويض من نفسه أبدًا. الـService دائمًا الحَكَم الوحيد.**

**نمطان مسموحان فقط:**

1. **الفعل المُغيِّر (Mutating Action)** — Filament يستدعي الـService مباشرة، **بلا أي `->authorize()`/`->visible()` كخط دفاع أساسي** — الـService يرمي `AuthorizationException` لو رُفِض، Filament يلتقطها ويعرض إشعارًا (نفس نمط `MembershipsRelationManager::runGuarded()` المُنفَّذ فعليًا بـPhase OI — **يبقى النمط المرجعي الإلزامي**).
2. **إخفاء بصري اختياري (UX فقط، لا أمان)** — لو أُضيف `->visible()` لإخفاء زر لفاعل غير مخوَّل غالبًا (تحسين تجربة، لا حماية)، **يجب يستخدم نفس Ability بالضبط** اللي يفحصها الـService (`Gate::check('manageSubscription', $organization)`) — **ممنوع فحص مختلف أو أوسع/أضيق** يخلق احتمال انحراف بين "الزر يظهر" و"الفعل يُقبَل".

**لماذا هذا يمنع الفجوة بنيويًا:** طالما مصدر القرار **واحد فقط** (الـService)، لا يوجد "مكانان يمكن أن يختلفا" — الفجوة اللي اكتُشفت بـ`OrganizationSubscriptionService` (قسم 2) حدثت **بالضبط** لأنه لم يوجد أي مصدر قرار إطلاقًا (لا بالـService ولا بـFilament) — لا لأن مصدرين تعارضا.

---

## 7. Attack Matrix

| # | السيناريو | النتيجة المصمَّمة | آلية المنع |
|---|---|---|---|
| 1 | Customer → `/admin` (أي مسار) | 🚫 رفض قبل عرض أي صفحة | `canAccessPanel()` (قسم 1) |
| 2 | Customer → استدعاء `OrganizationSubscriptionService` مباشرة (تجاوز كامل لـFilament — Tinker/كود مستقبلي) | 🚫 `AuthorizationException` | Gate داخل الـService نفسه (قسم 2) — **لا علاقة لـFilament بهذا المنع إطلاقًا**، هذا بالضبط سبب الإصلاح |
| 3 | Member بمؤسسة A → فعل على مؤسسة B | 🚫 مرفوض | `OrganizationPolicy` يتحقق من `organization_id` المستهدَف صراحة (نمط AD-012 الموجود) |
| 4 | Admin بمؤسسة A → فعل على مؤسسة B | 🚫 مرفوض (Admin ليست Staff ولا Owner لـB) | نفس أعلاه |
| 5 | Staff → مؤسسة بلا Owner حقيقي إطلاقًا (Org 1/Org 2-like) | ✅ مسموح (بالضبط الحالة اللي Option D صُمِّم لحلها) | شرط `OR isPlatformStaff()` |
| 6 | Staff → Hard Delete Organization | 🚫 **لا مسار له بالـDomain كليًا** | غياب بنيوي — لا Method بالـService، لا Action بـFilament (Phase OL) |
| 7 | تلاعب مباشر بمسار URL (`/admin/organizations/{other_id}/...`) | 🚫 مرفوض | Route Model Binding + تحقق الـService الداخلي (Defense in Depth، مطابق لأنماط Phase 2B/OI الموجودة) |
| 8 | استدعاء مباشر للـService بمعزل تام عن أي HTTP (نفس #2 لكن بسياق عملياتي — مثال: أمر Artisan مستقبلي يُشغَّل بالخطأ بفاعل خاطئ) | 🚫 `AuthorizationException` | نفس آلية #2 — **هذا بالضبط الفرق بين "حماية Filament" و"حماية الـDomain نفسه"** |
| 9 (إضافي) | Staff يحاول تخفيض/حذف آخر Owner دون Transfer Ownership | 🚫 مرفوض (Last Owner Rule لا تتأثر بكون الفاعل Staff) | `MembershipService::assertNotLastOwner` — **قاعدة عمل منفصلة تمامًا عن Authorization**، لا استثناء لـStaff |

---

## 8. الاختبارات المطلوبة قبل التنفيذ (تصميم فقط، لا كتابة الآن)

| الفئة | الاختبار |
|---|---|
| **Platform-level** | Staff يدخل `/admin` بنجاح · Customer يُرفَض · زائر غير مصادَق يُرفَض قبل ذلك أصلًا |
| **Service-level (الإصلاح الأهم)** | `OrganizationSubscriptionService::create/changeSeatLimit/cancel` تُلقي `AuthorizationException` لفاعل Customer (لا Membership إطلاقًا) — **بمعزل تام عن Filament** (استدعاء مباشر بالاختبار) |
| **Owner لا يزال يعمل (Regression)** | نفس التوابع الثلاثة تنجح لفاعل Owner حقيقي — يثبت الإصلاح لم يكسر المسار الصحيح |
| **Staff Bypass** | نفس التوابع تنجح لفاعل Staff بلا أي Membership إطلاقًا |
| **Staff ≠ Owner (قسم 3)** | بعد فعل إداري بواسطة Staff، لا `Membership.role=Owner` جديدة تُنشأ له، لا `AccessAssignment` |
| **Cross-Org (Attack #3/#4)** | Member/Admin بمؤسسة A يُرفَضان صراحة لفعل على مؤسسة B — تحديدًا لتوابع `OrganizationSubscriptionService` (لم تُختبَر لهذا السيناريو سابقًا لأنها لم يكن بها Gate أصلًا) |
| **Bootstrap Command** | يرفض بريدًا غير موجود · ينجح لبريد موجود · `--revoke` يعكس الحالة بنجاح |
| **Regression الكامل** | 169 اختبار حالي يبقون 169/169 |

---

## 9. ما لا تلمسه هذي المرحلة (تأكيد)

❌ Phase OL (Archive/Restore تبقى غير مُفعَّلة للتنفيذ حتى تُعتمَد بمعزل — هذي الوثيقة تُحضِّر الأساس الذي تحتاجه لاحقًا فقط). ❌ Header/Dashboard/Navigation/Marketplace UI. ❌ `owner_id` schema أو إصلاح Org 1/Org 2. ❌ `EntitlementResolver`. ❌ بناء `UserResource`/واجهة إدارة Staff بالواجهة.

---

## Open Decisions — محسومة قبل التنفيذ

1. **تسمية Log Channel لأمر Bootstrap** (قسم 1.3) — **محسوم:** قناة مخصَّصة باسم `platform_security` (`config/logging.php`)، تكتب لملف مستقل `storage/logs/platform-security.log`، بمعزل عن `laravel.log` العام لسهولة المراجعة الأمنية.
2. **توقيت تفعيل الإصلاح على `changeSeatLimit()`** (قسم 2.2، Breaking Change على التوقيع) — **محسوم:** بنفس تشغيلة إصلاح Authorization لباقي التوابع الثلاثة، لا خطوة منفصلة (اعتماد صريح من المستخدم — تجنّب أي نافذة يكون فيها `create()`/`cancel()` محميين بينما `changeSeatLimit()` ما زال يعتمد على Filament فقط).

**لا قرار معماري حقيقي معلَّق — الاتجاه العام (Option D)، القيد الأهم (Staff≠Owner)، ورفض `Gate::before()`، وقناة الـLog، وتوقيت `changeSeatLimit()` — كلها محسومة. التصميم جاهز للتنفيذ.**
