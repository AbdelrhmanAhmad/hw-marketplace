# Platform Authorization Foundation — مراجعة أمنية مستقلة

**النطاق:** مراجعة عدائية للكود الفعلي (Routes/Policies/Services/Filament Resources/RelationManagers/Actions/Vendor Source)، **بمعزل عن الاعتماد على الاختبارات الموجودة**. صفر تعديل كود أو قاعدة بيانات خلال هذي المراجعة.
**المنهجية:** قراءة مباشرة لكل ملف مرتبط، تتبع كل مسار HTTP/Livewire/CLI ممكن يصل لعملية حساسة، تحقّق من سلوك Filament/Livewire الفعلي عبر مصدر الـVendor نفسه (لا افتراض)، تتبّع كل استخدام لـ`is_platform_staff` بالكود كاملًا.

---

## الحكم النهائي

# 🔴 SECURITY REVIEW: BLOCKED

**السبب الأساسي:** Finding #1 (Critical-adjacent High) — ثغرة حقيقية، قابلة للاستغلال عمليًا بخطوة واحدة من أي Platform Staff، تسمح بمنح عضوية Owner دائمة (تنجو من سحب صلاحية Staff) بمعزل تام عن `MembershipService` وبصفر تدقيق (Audit). هذي الثغرة تُبطِل عمليًا الوعد المركزي لهذي المرحلة: أن Platform Staff محور صلاحية **قابل للسحب** ومنفصل عن Owner. لا يمكن اعتماد "الحد الأمني" مكتملًا وهذا المسار مفتوح.

**ما يلزم للانتقال لـPASS:** إصلاح Finding #1 حصرًا يكفي تقنيًا لإغلاق البلوكر الأساسي. يوصى بشدة (لا إلزام لإغلاق هذي المرحلة تحديدًا) بمعالجة Finding #2 (`SeatService`) بنفس الدفعة أو قبل بدء Phase OL، لأنه نفس فئة الثغرة تمامًا التي بُنيت هذي المرحلة أصلًا لإغلاقها.

---

## ملخص الـFindings

| # | العنوان | الخطورة | Exploitable | Regression؟ | يمنع الإغلاق؟ |
|---|---|---|---|---|---|
| 1 | Membership `CreateAction` يتجاوز `MembershipService` بالكامل — منح Owner دائم بلا Authorization ولا Audit | **High** | فعليًا (خطوة واحدة) | لا (سابق لهذي المرحلة، لكن أثره الأمني تفاقم بسبب هذي المرحلة) | **نعم** |
| 2 | `SeatService` لا يملك أي `Gate::authorize()` داخلي — يثق بالكامل بالمُستدعي | **High** | نظريًا (لا مسار مباشر اليوم، لكن نفس فئة الثغرة المُصلَحة توًا بمكان آخر) | لا (Phase 2B، خارج نطاق هذي المرحلة صراحة) | لا (لكن يُوصى بشدة) |
| 3 | تناقض ترتيب الفحوصات بـ`OrganizationSubscriptionService` (Authorization قبل/بعد Validation حسب التابع) | Medium | لا (تسريب معلومة هامشي فقط) | لا (جزء من هذي المرحلة، عيب تصميم بسيط) | لا |
| 4 | `livewire/update` لا يمر بحاجز `canAccessPanel()` — نافذة Session قديمة بعد سحب Staff | Medium | ضيق جدًا (يتطلب جلسة Staff مفتوحة مسبقًا) | لا (سلوك Livewire/Filament أصلي) | لا |
| 5 | Membership Create/إنشاء غير مُدقَّق (Audit) — امتداد لـAD-016 المؤجَّل | Medium | — (فجوة تدقيق، لا صلاحية) | لا (امتداد لفجوة معروفة، مؤجَّلة بقرارك) | لا |
| 6 | `RequestPasswordReset` يُسقِط بريد الاستعادة بصمت لغير الـStaff | Low | لا (ليس Bypass) | لا (سلوك Filament أصلي) | لا |
| 7 | `AppSubscriptionResource` (Legacy) ما زال CRUD مباشر بلا Domain Service | Low/Informational | خارج النطاق | لا (Phase 1، غير ذي علاقة بهذي المرحلة) | لا |
| 8 | `Organization.status` قابل للـMass Assignment نظريًا لو أُضيف مستقبلًا لأي Form | Low/Theoretical | لا (لا تعرّض حاليًا) | لا | لا |

**تأكيدات إيجابية (Confirmed Safe)** موثَّقة بقسم 3 — عناصر فحصتها ووجدتها سليمة فعليًا، لا افتراضًا.

---

## 1. Finding #1 — Membership CreateAction يتجاوز MembershipService بالكامل (High)

### الدليل من الكود

`app/Filament/Resources/OrganizationResource/RelationManagers/MembershipsRelationManager.php:59-61`:

```php
->headerActions([
    Tables\Actions\CreateAction::make(),
]),
```

بلا `->using()` — بعكس كل فعل آخر بنفس الملف (`EditAction`, `transferOwnership`, `DeleteAction`, `DeleteBulkAction`) واللي جميعها تمر عبر `runGuarded()` → `MembershipService`. هذا يعني `CreateAction` يستخدم السلوك الافتراضي لـFilament: `Membership::create($data)` مباشرة.

**تحققت إنه فعليًا قابل للتنفيذ (لا Mass Assignment يحجبه):**
`app/Models/Membership.php:11` — `#[Fillable(['user_id', 'organization_id', 'role'])]` — الحقول الثلاثة اللي يملأها الـForm (`user_id`, `role`) + `organization_id` (تلقائي من الـRelationManager) **كلها Fillable**. لا حاجز.

**تحققت أنه لا يوجد أي حاجز Policy بديل:**
`app/Policies/` يحتوي `OrganizationPolicy.php` فقط — **لا يوجد `MembershipPolicy` إطلاقًا**. حتى لو وُجد، Filament's `authorize()` helper (`vendor/filament/filament/src/helpers.php:16-48`) يُرجِع **Allow افتراضيًا** لو الـPolicy Class غير موجود أو التابع المحدَّد غير معرَّف فيه (مُتحقَّق من الكود مباشرة، ليس افتراضًا):

```php
if (($policy === null) || (! method_exists($policy, $action))) {
    $response = invade(Gate::forUser($user))->callBeforeCallbacks($user, $action, [$model]);
    if ($response === false) { throw new AuthorizationException; }
    if (! $response instanceof Response) { return Response::allow(); }  // ← الافتراضي هنا
    ...
}
```

بما إنه لا يوجد `Gate::before()` بالمشروع كاملًا (تحققت بـ`grep -rn "Gate::before" app/` → صفر نتائج)، `$response` تبقى `null` → **Allow دائمًا**.

### Attack Path

1. أي Platform Staff (الفاعل المطلوب أصلًا للوصول لـ`/admin` — لا حاجة لأي صلاحية إضافية) يفتح `المكاتب والمؤسسات` → أي مؤسسة → تبويب "الأعضاء".
2. يضغط "جديد"، يختار أي مستخدم (بما فيه نفسه)، يحدد الدور `Owner`، يحفظ.
3. **النتيجة:** صف `Membership` جديد بـ`role=Owner` لتلك المؤسسة، **بلا أي استدعاء لـ`MembershipService`، بلا `Gate::authorize()` مخصَّص، بلا سجل `AuditLog`.**

### لماذا هذا أخطر من مجرد "فجوة CRUD عادية"

`OrganizationPolicy` (بعد هذي المرحلة) تمنح الصلاحية بشرط: `isPlatformStaff() OR Membership.role=Owner`. **عضوية Owner حقيقية بجدول `memberships` هي مصدر صلاحية مستقل تمامًا وباقٍ حتى لو انسحبت صلاحية Staff لاحقًا** (`platform:grant-staff --revoke`). يعني: Staff يقدر يمنح نفسه (أو أي حساب آخر يتحكم به) **صلاحية دائمة غير قابلة للسحب عبر آلية سحب Staff** على أي مؤسسة — بخطوة واحدة، صفر أثر مُدقَّق. هذا يُبطِل عمليًا الوعد الجوهري لهذي المرحلة ("Platform Staff محور صلاحية منفصل وقابل للسحب").

### هل Regression من مرحلة سابقة؟

**لا بمعنى الكود** — هذا المسار موجود منذ Phase OI (لم يُلمَس CreateAction حينها، فقط Edit/Delete/transferOwnership). **لكن نعم بمعنى الخطورة الفعلية**: قبل هذي المرحلة كان `/admin` بالكامل مفتوحًا لأي مستخدم مسجَّل دخول، فهذا المسار كان جزءًا من مشكلة أشمل ("كل شيء مفتوح"). الآن وبعد بناء حد أمني قائم بالكامل على افتراض "Staff محور منفصل وقابل للسحب"، هذا المسار تحديدًا أصبح **الثغرة الوحيدة الباقية اللي تكسر ذاك الافتراض تحديدًا**.

### Exploitable فعليًا أم نظري؟

**فعليًا.** خطوة واحدة، بلا شروط خاصة، بواسطة أي Staff حالي.

### التوصية

لف `CreateAction` بـ`->using()` يستدعي تابع جديد على `MembershipService` (مثلًا `add(User $actor, Organization $organization, User $target, MembershipRole $role)`) يتحقق داخليًا عبر `Gate::forUser($actor)->authorize('manageMembers', $organization)` ويسجّل `AuditLog` — بنفس النمط المُطبَّق حرفيًا على باقي أفعال نفس الملف. **هذا يغلق Finding #1 وFinding #5 معًا بتغيير واحد.**

### Security Test مقترح

```php
public function test_staff_cannot_grant_owner_membership_bypassing_membership_service(): void
{
    // يجب أن يفشل هذا الاختبار اليوم (يثبت الفجوة)، وينجح بعد الإصلاح.
    $staff = User::factory()->create(['is_platform_staff' => true]);
    $organization = Organization::create([...]);
    $target = User::factory()->create();

    Livewire::actingAs($staff)
        ->test(MembershipsRelationManager::class, ['ownerRecord' => $organization, 'pageClass' => EditOrganization::class])
        ->callTableAction('create', data: ['user_id' => $target->id, 'role' => 'owner']);

    // بعد الإصلاح: يجب وجود AuditLog لهذا الإنشاء، أو رفض الفعل لو الفاعل ليس Owner/Staff مخوَّلًا فعليًا (بديهي هنا لأنه Staff).
    $this->assertTrue(AuditLog::where('event', 'membership_created')->exists()); // Event جديد يلزم تعريفه
}
```

---

## 2. Finding #2 — SeatService لا يملك أي Authorization داخلي (High، خارج النطاق الحرفي لهذي المرحلة)

### الدليل من الكود

`app/Services/SeatService.php` — قرأته كاملًا: **لا استيراد لـ`Gate` (لا `use Illuminate\Support\Facades\Gate;` بالملف إطلاقًا)، ولا استدعاء `Gate::authorize()`/`Gate::forUser()` بأي تابع** (`assign`, `release`, `reassign`, `releaseAllForUserInOrganization`). التحقق الوحيد الموجود هو تحقق **Domain-rule** (هل المستهدَف عضو أصلًا، هل يوجد مقعد متاح) — **لا تحقق Authorization إطلاقًا**.

الحماية الوحيدة اليوم: `app/Http/Controllers/OrganizationSeatController.php:28,46,63` — `$this->authorize('manageSeats', $organization)` **بمستوى الـController فقط**، قبل استدعاء `SeatService`.

### Attack Path (نظري اليوم، حقيقي لو تغيّر أي شيء غدًا)

`SeatService` مسجَّلة بحاوية Laravel (`app(SeatService::class)`) — أي كود مستقبلي (Job، Command جديد، Endpoint API لم يُبنَ بعد، حتى Tinker) يستدعيها مباشرة **يتجاوز التحقق كليًا**، بالضبط نفس النمط اللي كان موجودًا بـ`OrganizationSubscriptionService` قبل إصلاح هذي المرحلة (والذي وثّقناه بـ`docs/platform-administration-authorization-design.md` كفجوة حرجة).

### هل هذا Regression؟

لا — موجود منذ Phase 2B، **لم تطلب هذي المرحلة صراحة إصلاحه** (المواصفة سمّت `OrganizationSubscriptionService` فقط). لكن السؤال الصريح المطروح بهذي المراجعة ("أي Service حساس لا يزال يعتمد ضمنيًا على Filament/Controller كـsecurity perimeter") ينطبق عليه حرفيًا.

### هل يمنع إغلاق هذي المرحلة؟

**لا، تقنيًا** — خارج النطاق المُصرَّح به صراحة بالمواصفة المعتمَدة، والمسار الوحيد المستغِل له اليوم (`OrganizationSeatController`) محمي فعليًا. لكن أوصي بشدة بمعالجته **قبل أو خلال Phase OL** (لأن Phase OL توسّع مسؤوليات Domain Services بنفس الاتجاه)، بنفس نمط الإصلاح المطبَّق على `OrganizationSubscriptionService` بالضبط.

### Security Test مقترح

```php
public function test_customer_calling_seat_service_directly_is_rejected(): void
{
    [$owner, $organization, $subscription] = $this->orgWithActiveSubscription();
    $customer = User::factory()->create();
    $target = User::factory()->create();
    Membership::create(['user_id' => $target->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]);

    $this->expectException(AuthorizationException::class); // يفشل اليوم — SeatService لا يرمي هذا الاستثناء إطلاقًا
    app(SeatService::class)->assign($customer, $subscription, $target);
}
```

---

## 3. Finding #3 — تناقض ترتيب الفحوصات بـOrganizationSubscriptionService (Medium)

### الدليل

`app/Services/OrganizationSubscriptionService.php`:
- `create()` سطر 36: `Gate::authorize()` **أولًا**، ثم Validation (سطر 38-44).
- `changeSeatLimit()` سطر 84-86: فحص `subscriber_type` **أولًا**، ثم `Gate::authorize()` (سطر 88).
- `cancel()` سطر 105-107: نفس النمط — Validation قبل Authorization.

### الأثر

ضئيل: فاعل غير مخوَّل يستدعي `changeSeatLimit()`/`cancel()` بـ`Subscription` من نوع غير `organization` يحصل على `InvalidArgumentException` (يكشف "هذا الاشتراك ليس مؤسسيًا") **قبل** ما يُكتشَف إنه أصلًا غير مخوَّل — تسريب معلومة هامشي جدًا (لا بيانات حساسة)، لا Bypass حقيقي لأي شيء.

### التوصية

توحيد الترتيب: `Gate::authorize()` دائمًا أول سطر بكل تابع مُغيِّر، بلا استثناء — مطابقة لنمط `create()` نفسه.

### Security Test مقترح

```php
public function test_authorization_check_runs_before_any_business_validation_in_all_three_methods(): void
{
    // اختبار بنيوي: يتحقق عبر Reflection أو ترتيب استدعاءات موثَّق أن أول سطر فعلي بكل تابع هو Gate::authorize().
}
```

---

## 4. Finding #4 — livewire/update لا يمر بحاجز canAccessPanel() (Medium)

### الدليل من الكود (Ground Truth، لا افتراض)

`vendor/livewire/livewire/src/Mechanisms/HandleRequests/HandleRequests.php:22-23`:
```php
return Route::post('/livewire/update', $handle)
    ->middleware('web');
```

**مسار عام واحد لكل مكوّنات Livewire بالتطبيق كاملًا، خارج بادئة `/admin`، ومسجَّل بـ`web` Middleware فقط — لا `Filament\Http\Middleware\Authenticate` (المسجَّلة حصرًا على مجموعة Routes الخاصة بالـPanel نفسه عبر `AdminPanelProvider::authMiddleware()`).**

بالمقابل، تحققت إن `Filament\Http\Middleware\Authenticate::authenticate()` (`vendor/filament/filament/src/Http/Middleware/Authenticate.php:16-35`) يُعيد فحص `$user->canAccessPanel($panel)` **بكل طلب HTTP كامل لأي صفحة `/admin/*`** (ليس فقط عند تسجيل الدخول) — هذا مؤكَّد ومطابق لاختبارات `PlatformStaffAccessTest`.

### Attack Path (ضيق، يتطلب حالة سابقة محدَّدة)

1. مستخدم Staff يفتح تبويب متصفح على `/admin/organizations/{id}/edit` (تحميل صفحة كامل → يمر بـ`Authenticate` middleware → يمرّ لأنه Staff، مكوّن Livewire يُحمَّل بـSnapshot موقَّع).
2. **أثناء** بقاء التبويب مفتوحًا، تُسحَب صلاحية Staff منه (`platform:grant-staff --revoke`).
3. لو ضغط زر "أرشفة" (فعل Livewire AJAX، لا تحميل صفحة كامل) — الطلب يذهب لـ`/livewire/update`، **بلا إعادة فحص `canAccessPanel()`**.

### لماذا هذا **لا** يُعرِّض الأفعال الحساسة الثلاثة عمليًا (تحقّقت، لم أفترض)

`Auth::user()` (المُمرَّر كـ`$actor` داخل كل الأفعال) يُحلَّل من الجلسة **بشكل متجدِّد بكل طلب HTTP منفصل**، شاملًا طلبات `/livewire/update` — هذا سلوك Laravel قياسي (لا Cache للمستخدم المُصادَق عبر طلبات منفصلة). يعني: `OrganizationLifecycleService::archive()` سيستدعي `Gate::forUser($actor)->authorize('archive', ...)` حيث `$actor->isPlatformStaff()` تُقرَأ **طازجة من قاعدة البيانات بلحظة الطلب**، فترجع `false` بعد السحب — **الفعل يُرفَض صحيحًا رغم تجاوز حاجز الـPanel**. هذا مؤكَّد بالكود (Gate::authorize موجود داخل كل من الأفعال الثلاثة لـSubscription + الأربعة لـMembership/Lifecycle)، وليس افتراضًا.

**الأثر الفعلي محصور بالأفعال اللي تعتمد على حاجز الـPanel وحده بلا فحص Domain مستقل**: بالضبط Finding #1 (Membership CreateAction) + تعديل الحقول الأساسية بمؤسسة (name/type، حساسية منخفضة) + الموارد الثمانية الأخرى للكتالوج (بالتصميم، مقصودة).

### هل هذا Regression؟

لا — سلوك Livewire/Filament أصلي، غير مرتبط بأي كود أضفته هذي المرحلة.

### التوصية

لا حاجة لإصلاح بنيوي (يتطلب تعديل Middleware عام على مستوى المشروع، أثر واسع، خارج نطاق موافقتك الحالية). يكفي إصلاح Finding #1 ليُزال الأثر العملي الوحيد ذو الدلالة. إن أردت تقليل النافذة الزمنية لهذا السيناريو مستقبلًا، الخيار المعياري هو تفعيل `session.lifetime` قصيرة نسبيًا لهذا الـGuard — لا أقترحه الآن لأنه تغيير تشغيلي مستقل يستحق قرارًا صريحًا منفصلًا.

### Security Test مقترح

```php
public function test_livewire_action_by_recently_revoked_staff_is_still_rejected_by_domain_gate(): void
{
    $staff = User::factory()->create(['is_platform_staff' => true]);
    $organization = Organization::create([...]);
    Membership::create(['user_id' => $staff->id, 'organization_id' => $organization->id, 'role' => MembershipRole::Lawyer]); // لا Owner

    $component = Livewire::actingAs($staff)->test(EditOrganization::class, ['record' => $organization->id]);
    $staff->forceFill(['is_platform_staff' => false])->save(); // سحب أثناء الجلسة، محاكاة Tab مفتوح
    $component->callAction('archive');

    $this->assertSame('active', $organization->fresh()->status); // يجب ألا تُؤرشَف
}
```

---

## 5. Finding #5 — إنشاء Membership غير مُدقَّق (Medium، امتداد لـAD-016)

مرتبط مباشرة بـFinding #1: طالما `CreateAction` لا يمر عبر أي Service، **لا يوجد سجل `AuditLog` لإنشاء أي عضوية جديدة إطلاقًا** — بينما `AD-016` (المُسجَّلة والمؤجَّلة بقرارك الصريح بعد Phase OI) وثّقت الفجوة لتغييرات الدور/الإزالة فقط. هذي المراجعة تؤكد إن **الفجوة تمتد أيضًا للإنشاء**. لا قرار جديد يلزم اتخاذه هنا (نفس القرار المؤجَّل ينطبق) — فقط توثيق إن نطاقها أوسع مما سُجِّل أصلًا. **سيُغلَق تلقائيًا لو أُصلِح Finding #1** بالطريقة المقترحة (تسجيل AuditLog داخل التابع الجديد).

---

## 6. Finding #6 — Filament Login/Password-Reset Enumeration (Low — مُقيَّم صراحة كما طلبت)

### هل هو خطر فعلي أم Hardening Opportunity فقط؟ **الجواب الصريح: ليس خطرًا أمنيًا، Hardening Opportunity بسيط فقط.**

**تحقق #1 — تسجيل الدخول:** `vendor/filament/filament/src/Pages/Auth/Login.php:65-78` — كلمة مرور خاطئة **و** رفض `canAccessPanel()` كلاهما يُنتِجان **بالضبط نفس رسالة الخطأ العامة** (`throwFailureValidationException()`). **صفر تمييز ممكن لمهاجم خارجي.** هذا حماية أصلية من Filament، لا نحتاج نبنيها.

**تحقق #2 — استعادة كلمة المرور:** `vendor/filament/filament/src/Pages/Auth/PasswordReset/RequestPasswordReset.php:66-74` — لو `canAccessPanel()` ترجع `false`، يتم تجاوز إرسال البريد **بصمت** (السطر 73 `return;` داخل الـClosure)، لكن استجابة الطلب نفسها (`$status`) تعتمد على نتيجة `Password::broker()->sendResetLink()` والتي عادة تُرجِع "تم الإرسال" **بصرف النظر** عن نجاح الإشعار الفعلي (التوكن يُنشأ بغض النظر). **الأثر العملي:** مستخدم غير-Staff يطلب استعادة كلمة مرور لحساب `/admin` يرى رسالة نجاح لكن **لا يستلم أي بريد أبدًا** — تجربة مربكة (Support Ticket محتمل)، **وليس تسريب معلومة لمهاجم** (لا يفرّق بين "الحساب غير موجود" و"الحساب موجود لكن ليس Staff" — كلاهما نفس الاستجابة الظاهرية للطالب).

**الخلاصة:** لا يمنع الإغلاق. توصية اختيارية فقط: عرض رسالة توضيحية مختلفة لمستخدم مسجَّل دخول بالفعل يحاول الوصول لـ`/admin` (خارج نطاق هذي المراجعة، تحسين UX لا أمان).

---

## 7. Finding #7 — AppSubscriptionResource (Legacy) — خارج النطاق (Low/Informational)

`app/Filament/Resources/AppSubscriptionResource.php` ما زال يسمح بـCreate/Edit مباشر على جدول `app_subscriptions` القديم (Phase 1) بلا أي Domain Service — يتقاطع مفهوميًا مع قرار "L1 Legacy Write Cutoff" (اللي أوقف الكتابة التلقائية عبر `FreeAppProvisioner`)، لكنه **لا علاقة له بـOrganization/Platform Staff Authorization** (نطاق هذي المراجعة تحديدًا). أذكره للأمانة فقط — لا يلزم قرارًا الآن، ولا يمنع إغلاق هذي المرحلة.

---

## 8. Finding #8 — Organization.status نظريًا عرضة لتوسّع مستقبلي (Low/Theoretical)

`app/Models/Organization.php:11` — `#[Fillable(['name', 'type', 'owner_id', 'status'])]` — `status` **Fillable فعليًا** على مستوى الـModel. **تحققت اليوم إنه غير مُعرَّض بأي Form فعلي**: `OrganizationResource::form()` (المُستخدَم بصفحتي Create/Edit) لا يحتوي حقل `status` إطلاقًا (فقط `name`, `type`, `owner_id` المعطَّل). **لا Bypass قائم اليوم.** لكن هذا اعتماد على "لا أحد يضيف حقل status لاحقًا لهذا الـForm" وليس ضمانة بنيوية — أي إضافة مستقبلية غير حذرة لحقل كهذا تتجاوز `OrganizationLifecycleService` بالكامل (بلا Audit، بلا إلغاء اشتراكات). أذكره كملاحظة دفاعية فقط، لا فجوة فعلية اليوم.

---

## 9. تأكيدات إيجابية — فحصتها ووجدتها سليمة فعليًا

هذا القسم بنفس أهمية الـFindings — نتائج فحص إيجابية، لا افتراضات:

1. **`canAccessPanel()` هو الحاجز الوحيد للدخول لـ`/admin`، ويُعاد فحصه بكل طلب HTTP كامل** (لا فقط تسجيل الدخول) — مؤكَّد من `Filament\Http\Middleware\Authenticate` مباشرة، ومطابق تمامًا لسحب صلاحية Staff أثناء جلسة نشطة (طلب صفحة جديد = رفض فوري، وليس فقط بالدخول التالي).
2. **`routes/web.php` لا يحتوي أي مسار آخر يصل لعمليات Organization/Subscription/Seat الحساسة بلا Authorization خاص به** — `OrganizationSeatController` و`OrganizationContextController` كلاهما يستدعيان `$this->authorize()`/فحصًا داخليًا صريحًا، بمعزل تام عن Filament.
3. **`create()`, `changeSeatLimit()`, `cancel()` على `OrganizationSubscriptionService` محميّة فعليًا عند الاستدعاء المباشر** (لا فقط من Filament) — مؤكَّد بقراءة الكود مباشرة (Finding #3 يخص الترتيب فقط، لا غياب الفحص).
4. **`MembershipService::changeRole/remove/transferOwnership` و`OrganizationLifecycleService::archive/restore` كلها محمية بـGate::authorize() داخلي حقيقي**، غير معتمدة على Filament إطلاقًا.
5. **`is_platform_staff` غير قابل للتعديل عبر Mass Assignment من أي مسار** — غير موجود بـ`#[Fillable]` على `User` (يتطلب `forceFill()` صراحة، مستخدَم فقط بأمر `platform:grant-staff`). تحققت من `RegisteredUserController::store()` و`ProfileController::update()` — كلاهما يستخدمان مصفوفات Whitelist صريحة (`$request->name/email/password`، `$request->validated()`)، **ليس `$request->all()`** — صفر مسار حتى نظري لحقن هذا الحقل عبر التسجيل أو الملف الشخصي.
6. **`platform:grant-staff` غير قابل للاستدعاء من أي HTTP Route أو Job أو Scheduler** — تحققت بـ`grep` شامل عبر `app/`, `routes/`, `database/` — لا استدعاء `Artisan::call()` له بأي مكان، `routes/console.php` لا يحتوي أي جدولة له.
7. **لا `Gate::before()` بالمشروع كاملًا** — تحققت بـ`grep -rn "Gate::before" app/` → صفر نتائج. الالتزام بشرط OR صريح بكل تابع Policy محقَّق فعليًا، لا نظريًا فقط.
8. **`EntitlementResolver` معزولة تمامًا عن `is_platform_staff`** — تحققت بقراءة الملف كاملًا وبـ`grep`: صفر إشارة. AD-005 (Entitlement ≠ Authorization) وAD-013 (EntitlementResolver مصدر القرار الوحيد) لم يُخرَقا — Staff لا يكتسب أي "أهلية استخدام" لأي عنصر Marketplace بمجرد كونه Staff.
9. **AD-012 (Active Organization Context ≠ مصدر صلاحية) لم يُخرَق** — كل كود جديد بهذي المرحلة (`OrganizationPolicy`, الخدمات الثلاث) يستعلم `Membership::query()` مباشرة بقاعدة البيانات، **صفر استخدام لـ`ActiveOrganizationContext`** بأي منها.
10. **IDOR/تلاعب بمعرّف مؤسسة**: كل تابع بـ`OrganizationPolicy` الستة يفحص `->where('organization_id', $organization->id)` صراحة — لا اعتماد على "عضوية بأي مؤسسة". مؤكَّد بقراءة كل تابع + اختبار Attack #7 بالتقرير السابق.
11. **Platform Staff لا يصبح Owner/Member تلقائيًا بأي كود جديد** — صحيح حرفيًا (لا كود يضيف Membership تلقائيًا لأي Staff). الاستثناء الوحيد هو Finding #1 — وهو فعل **يدوي اختياري** من Staff نفسه، لا تلقائي، لكنه يفتح نفس الأثر عمليًا.

---

## 10. تقييم صريح لجودة الاختبارات الحالية (كما طلبت — لا اعتماد أعمى)

- **194 اختبار الموجودة تثبت الطبقة اللي بُنيت لها فقط** — استدعاءات مباشرة لـServices (`OrganizationSubscriptionServiceTest`, `PlatformAuthorizationAttackMatrixTest`) أو طلبات HTTP كاملة لصفحات (`PlatformStaffAccessTest`). **لا يوجد ولا اختبار واحد يُشغِّل مكوّن Filament Livewire فعليًا عبر `Livewire::test()`** — تحديدًا السبب اللي خلّى Finding #1 غير مكتشَف بأي اختبار موجود، رغم إنه أخطر Finding بهذي المراجعة. هذا **Blind Spot بنيوي حقيقي بمنهجية الاختبار الحالية**، لا مجرد نقص عدد.
- **`PlatformGrantStaffCommandTest` تستخدم `Log::shouldReceive()` (Mock)** — فحصتها: الـAssertion الأساسي بكل اختبار هو حالة قاعدة بيانات حقيقية (`$user->fresh()->isPlatformStaff()`)، والـMock ثانوي (يتحقق من محتوى سجل التدقيق فقط) — **خطورة False Confidence منخفضة هنا تحديدًا**، لأن فشل الـMock لا يُخفي فشل التغيير الفعلي.
- **لا اختبار واحد يحاكي "جلسة Staff قديمة بعد سحب الصلاحية أثناء الجلسة"** (Finding #4) — فجوة تغطية حقيقية، الاختبار المقترح بقسم 4 يسدّها.
- **التوصية:** أي إصلاح لـFinding #1 يجب يُرفَق باختبار `Livewire::test()` حقيقي (مثال مقترح بقسم 1) — ليس استدعاء Service مباشرًا فقط — وإلا الثغرة نفسها (أو مشابهة لها بفعل Filament آخر مستقبلًا) ستبقى غير مكتشَفة بنفس الطريقة.

---

## 11. الخلاصة

**Platform Authorization Foundation نجحت فعليًا بإغلاق الهدفين المُصرَّح بهما صراحة** (`/admin` + `OrganizationSubscriptionService`) — الفحص العدائي لم يجد أي Bypass حقيقي لأي من الاثنين. **لكن المراجعة، بالنظر خارج النطاق الحرفي كما طلبت صراحة، وجدت مسارًا واحدًا حقيقيًا وقابلًا للاستغلال فعليًا** (Finding #1) يبطل الضمانة الأعمق اللي هذي المرحلة صُمِّمت لأجلها (فصل وقابلية سحب Platform Staff عن Owner). هذا وحده كافٍ لعدم اعتماد "الحد الأمني" مكتملًا اليوم.

# 🔴 SECURITY REVIEW: BLOCKED

بانتظار قرارك — لا Phase OL حتى تعتمد نتيجة هذي المراجعة بنفسك، كما طلبت.
