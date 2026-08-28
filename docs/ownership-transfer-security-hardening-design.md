# Ownership Authorization Unification — تصميم تنفيذ (قبل أي كود)

**الحالة:** تصميم فقط. **صفر كود.** يُنفَّذ فقط بعد موافقتك الصريحة على هذي الوثيقة.
**القرار المعتمَد من قبلك (AD-017، مُسجَّل بالفعل بـ`marketplace-architecture-blueprint.md`/`marketplace-implementation-specification.md`):** Platform Staff لا يملك أي صلاحية `transferOwnership()` على مؤسسة لها Owner حقيقي — بلا استثناء دعمي. الاستثناء الوحيد الباقي: مؤسسة بلا Owner حقيقي إطلاقًا.
**الدرس المُعمَّم من هذي الوثيقة:** لا نُصلِح مسارًا واحدًا فقط — نُثبت أن **كل** مسار موجود اليوم يمر عبر قاعدة موحَّدة واحدة، بدليل فحص شامل للكود الفعلي (لا الذاكرة).

---

## 1. Security Path Inventory — فحص شامل، لا من الذاكرة

طريقة الفحص: `grep` شامل لكل استدعاء `Membership::create(`/`Membership::` الثابتة، كل `->update(['role'`، كل مرجع لـ`MembershipRole::Owner`، وكل مرجع لكتابة `owner_id`، عبر `app/` كاملة، ثم قراءة كل نتيجة سطرًا سطرًا للتأكد إنها Read أو Write فعليًا.

### النتيجة الحاسمة: يوجد **3 نقاط كتابة فقط** على `Membership.role` بكامل الكود، **كلها داخل ملف واحد**

| # | الموقع | السطر | الفعل |
|---|---|---|---|
| 1 | `MembershipService::add()` | `app/Services/MembershipService.php:57-61` | `Membership::create([..., 'role' => $role])` |
| 2 | `MembershipService::changeRole()` | `app/Services/MembershipService.php:99` | `$locked->update(['role' => $newRole])` |
| 3 | `MembershipService::transferOwnership()` | `app/Services/MembershipService.php:157-158` | `$lockedTo->update(['role' => Owner])` + `$lockedFrom->update(['role' => $demoteFromTo])` |

**لا يوجد أي كتابة رابعة بأي مكان آخر** — تحققت بـ`grep -rn "Membership::"` شاملة على `app/` كاملة: كل استدعاء آخر (`OrganizationResource.php`, `MembershipsRelationManager.php`, `OrganizationSeatController.php`, `SeatService.php`) هو `::query()`/`::where()`/`::findOrFail()` — قراءة فقط. **لا Seeders تلمس Membership، لا Console Commands، لا Observers، لا Model Events.**

### `owner_id` (الحقل القديم على `Organization`) — تأكيد إضافي

`owner_id` **ليس** مصدر صلاحية (مقرَّر منذ Owner Integrity Hardening). حقل الإدخال الوحيد له بـFilament (`OrganizationResource.php:51-56`) مُعطَّل صراحة (`disabled()->dehydrated(false)`) — **لا مسار كتابة حي له اليوم** غير القيمة الأولية عند `Organization::create()` (خارج أي منطق Membership، ولا يُستخدَم لأي قرار Authorization). مؤكَّد، لا علاقة له بهذي الوثيقة.

### نقاط الاستدعاء (Entry Points) لكل تابع من الثلاثة

| التابع | مسارات الاستدعاء المؤكَّدة اليوم |
|---|---|
| `add()` | `MembershipsRelationManager::CreateAction` (Filament/Livewire) + استدعاء مباشر (Tinker/أي كود مستقبلي) |
| `changeRole()` | `MembershipsRelationManager::EditAction` (Filament/Livewire) + استدعاء مباشر |
| `transferOwnership()` | `MembershipsRelationManager::Action::make('transferOwnership')` (Filament/Livewire) + استدعاء مباشر |

**لا يوجد Route HTTP مخصَّص، لا API endpoint، لا Job، يستدعي أيًّا من الثلاثة.** المسار الوحيد غير-Tinker لكل تابع هو نفس ملف RelationManager واحد.

### تغطية Authorization الحالية (بعد Hardening Pass، قبل هذا الإصلاح)

| التابع | الحالة الفرعية | التغطية اليوم |
|---|---|---|
| `add()` | `role = Owner` | ✅ `authorizeGrantingOwnership()` |
| `add()` | `role ≠ Owner` | ✅ `manageMembers` (Owner/Admin/Staff — مستوى صحيح، ليس منح Owner) |
| `changeRole()` | ترقية إلى Owner | ✅ `authorizeGrantingOwnership()` |
| `changeRole()` | أي تحوّل آخر (شامل تخفيض Owner) | ✅ `manageMembers` + `assertNotLastOwner()` (قاعدة عمل منفصلة، تحمي من صفر Owners بصرف النظر عن الفاعل) |
| `remove()` | حذف عضو | ✅ `manageMembers` + `assertNotLastOwner()` لو Owner |
| **`transferOwnership()`** | **ينتج عنه دائمًا Owner جديد** | ❌ **`Gate::authorize('transferOwnership', ...)` مباشرة — خارج `authorizeGrantingOwnership()`، هذا الفجوة (Finding H1)** |

**التأكيد الحاسم:** `transferOwnership()` هي **المسار الوحيد المتبقي خارج القاعدة الموحَّدة اليوم.** لا يوجد مسار رابع مخفي — الفحص شامل الكود بأكمله، لا استنتاج جزئي.

---

## 2. القاعدة النهائية الموحَّدة

**لا قاعدة جديدة تُبنى — القاعدة الموجودة فعليًا (`authorizeGrantingOwnership()`) تُطبَّق على المسار الثالث المتبقي، بلا أي تعديل على منطقها الداخلي:**

```php
private function authorizeGrantingOwnership(User $actor, Organization $organization): void
{
    $organizationHasRealOwner = /* Membership.role=Owner exists */;

    if (! $organizationHasRealOwner) {
        Gate::forUser($actor)->authorize('transferOwnership', $organization); // Owner-or-Staff — تأسيس أول Owner فقط
        return;
    }

    $actorIsRealOwner = /* $actor نفسه Membership.role=Owner بنفس المؤسسة */;
    if (! $actorIsRealOwner) {
        throw new AuthorizationException(...); // Staff بمفرده لا يكفي
    }
}
```

**التغيير الوحيد المطلوب:** استبدال السطر الوحيد بـ`transferOwnership()`:
```php
// قبل:
Gate::forUser($actor)->authorize('transferOwnership', $from->organization);
// بعد:
$this->authorizeGrantingOwnership($actor, $from->organization);
```

**لماذا هذا يُطبِّق قرارك حرفيًا بلا أي منطق إضافي:** `transferOwnership()` بالتعريف تُستدعى فقط على مؤسسة **لها Owner بالفعل** (`$from` نفسه Membership بدور Owner — شرط مسبق للتابع أصلًا). يعني الفرع الأول (`! $organizationHasRealOwner`) **لن يتحقق أبدًا** لهذا المستدعي تحديدًا — النتيجة العملية دائمًا الفرع الثاني: **الفاعل يجب يكون Owner حقيقي بنفس المؤسسة، Staff بمفرده لا يكفي — بلا استثناء، بالضبط طلبك.**

**لا قاعدة ثانية، لا تسمية جديدة.** التابع يبقى `authorizeGrantingOwnership()` (الاسم يعكس الغرض بدقة: "من يملك حق منح صلاحية Owner" — يشمل الإنشاء المباشر والترقية والنقل، الثلاثة أشكال لنفس الفعل الجوهري).

**ملاحظة تسمية (لا تغيير مطلوب، للوضوح فقط):** الـPolicy Ability المُسمَّاة `transferOwnership` (بـ`OrganizationPolicy`) ستبقى، لكن استخدامها الفعلي بعد هذا الإصلاح يضيق لحالة واحدة فقط: تأسيس أول Owner بمؤسسة يتيمة (الفرع الأول أعلاه) — لم تعد تُستخدَم مطلقًا من تابع `transferOwnership()` نفسه. الاسم قد يبدو مربكًا (Policy ability اسمها `transferOwnership` لا تُستخدَم من `Service::transferOwnership()`) — **لم أُغيِّره لتجنّب توسيع نطاق هذا الإصلاح لمجرد تسمية**؛ أذكره فقط كملاحظة وضوح، قرار اختياري لك لو أردت إعادة تسمية لاحقًا (مثلًا `establishFirstOwnership`).

---

## 3. Security Matrix

### 3.1 — مؤسسة **لها Owner حقيقي بالفعل**

| Actor | Create Member (غير-Owner) | Create Owner (مباشرة) | Promote to Owner (changeRole) | Transfer Ownership | Demote Owner (ليس آخر) | Remove Owner (ليس آخر) | Remove Last Owner |
|---|---|---|---|---|---|---|---|
| **Owner** (نفس المؤسسة) | ✅ | ✅ (Owner إضافي) | ✅ | ✅ | ✅ | ✅ | ❌ (دائمًا، بلا استثناء لأي فاعل) |
| **Admin** | ✅ | ❌ | ❌ | ❌ | ✅* | ✅* | ❌ |
| **Member** (Lawyer/Accountant/إلخ) | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Platform Staff** (بلا Membership حقيقية بهذي المؤسسة) | ✅ | ❌ | ❌ | **❌ (بعد هذا الإصلاح — كانت ✅، هذي الفجوة)** | ❌ | ❌ | ❌ |
| **Unauthenticated** | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |

`*` — سلوك Phase OI الأصلي غير المُعدَّل بهذي الدفعة (`manageMembers` تشمل Admin لتخفيض/إزالة Owner ليس-الأخير) — مذكور بالمراجعة الثانية (Finding H4) كملاحظة للأمانة، **ليس جزءًا من نطاق هذا الإصلاح** ولا صلة له بمسار "منح Owner" (تخفيض/إزالة تُنقِص، لا تمنح).

### 3.2 — مؤسسة **بلا Owner حقيقي إطلاقًا**

| Actor | Create Owner (تأسيس أول Owner) |
|---|---|
| **Platform Staff** | ✅ (لا تغيير — هذا هو الاستثناء الوحيد المُبقى) |
| **Admin/Member موجود بالمؤسسة (لو وُجد استثنائيًا بلا Owner)** | ❌ (`manageMembers` لا تكفي لمنح Owner، `transferOwnership` Ability لا تشملهم) |
| **Unauthenticated/Customer خارجي** | ❌ |

---

## 4. Attack Matrix

| # | السيناريو | النتيجة اليوم (قبل هذا الإصلاح) | النتيجة بعد الإصلاح |
|---|---|---|---|
| 1 | Staff يضيف نفسه Admin ← `transferOwnership` لنفسه (Finding H1) | ✅ **ينجح — الثغرة** | ❌ `AuthorizationException` |
| 2 | Staff يستدعي `transferOwnership()` مباشرة (بلا Filament) على مؤسسة لها Owner | ✅ ينجح (نفس الثغرة، عبر Tinker) | ❌ `AuthorizationException` |
| 3 | Staff عبر زر "نقل الملكية" بـFilament (Livewire) على مؤسسة لها Owner | ✅ ينجح | ❌ مرفوض، إشعار واضح (`runGuarded`) |
| 4 | Staff يمنح Owner لمؤسسة **يتيمة** (لا Owner إطلاقًا) | ✅ يعمل | ✅ **يبقى يعمل — لا تغيير، الاستثناء المقصود** |
| 5 | Owner حقيقي ينقل ملكيته لعضو آخر بنفس مؤسسته | ✅ يعمل | ✅ يبقى يعمل (Regression Test) |
| 6 | Admin يحاول `transferOwnership()` | ❌ مرفوض أصلًا | ❌ يبقى مرفوضًا |
| 7 | Customer/Member خارجي يحاول أي مسار منح Owner | ❌ مرفوض أصلًا | ❌ يبقى مرفوضًا |
| 8 | Staff يحاول الالتفاف عبر `add()` مباشرة (بدل `transferOwnership`) على مؤسسة لها Owner | ❌ مرفوض أصلًا (مُصلَح بـHardening Pass الأول) | ❌ يبقى مرفوضًا |
| 9 | Staff يحاول الالتفاف عبر `changeRole()` مباشرة | ❌ مرفوض أصلًا (مُصلَح بـHardening Pass الأول) | ❌ يبقى مرفوضًا |
| 10 | سحب Staff بعد محاولة #1/#2/#3 الفاشلة | لا معنى له — لا شيء تغيّر أصلًا | لا معنى له — الرفض حدث *أثناء* المحاولة |

---

## 5. الاختبارات الجديدة المُقترَحة (وصف، لم تُكتَب بعد)

كلها تُضاف لـ`tests/Feature/Platform/PlatformAuthorizationHardeningTest.php` (الملف الموجود من الدفعة السابقة، امتداد طبيعي له):

1. **`test_staff_cannot_transfer_ownership_to_self_via_direct_service_call`** — يطابق سيناريو Attack #1/#2 حرفيًا (السيناريو اللي اكتشفه Security Review #2، يصبح Regression دائم كما طلبتَ): Staff يضيف نفسه Admin، يستدعي `transferOwnership()` مباشرة → `AuthorizationException` → لا تغيّر بالـOwner → لا `OwnershipGranted` جديد.
2. **`test_staff_cannot_transfer_ownership_via_livewire_action`** — نفس السيناريو، لكن عبر `Livewire::test()` الحقيقي على زر "نقل الملكية" بـ`MembershipsRelationManager` (يغطي Attack #3 — الطبقة اللي فاتت المرة الأولى).
3. **`test_staff_can_still_bootstrap_ownership_for_orphaned_organization`** — إعادة تأكيد صريحة (Attack #4): مؤسسة بلا Owner، Staff يمنح Owner لعضو مؤهَّل → ✅ ينجح، Owner واحد بالضبط بعدها، `OwnershipGranted`/`MembershipCreated` مُسجَّل.
4. **`test_real_owner_can_still_transfer_ownership`** — Regression صريح (Attack #5): Owner حقيقي ينقل الملكية → يبقى يعمل كما هو.
5. **`test_admin_still_cannot_transfer_ownership`** — Regression (Attack #6، اختبار موجود فعليًا `test_transfer_ownership_rejects_admin_actor_only_owner_allowed` بـ`MembershipServiceTest.php` — يبقى يمرّ بلا تعديل، يُذكَر هنا للتأكيد فقط).
6. **`test_staff_cannot_bypass_via_create_or_change_role_either`** — تجميع صريح لـAttack #8/#9 بملف واحد، تأكيد أن كل الأبواب الثلاثة (`add`/`changeRole`/`transferOwnership`) مغلقة بنفس القاعدة بنفس الوقت — يمنع تكرار "أصلحنا بابًا، فتح غيره" مستقبلًا (نفس درسك).

**لا اختبار Concurrency جديد مطلوب هنا** — `authorizeGrantingOwnership()` لا تُستدعى بمكان جديد يغيّر من مخاطر التزامن الموجودة أصلًا (نفس القفل، نفس المعاملة).

---

## 6. الملفات المتأثرة (لن تُعدَّل الآن — للعرض فقط)

| الملف | التغيير المقترَح |
|---|---|
| `app/Services/MembershipService.php` | سطر واحد بـ`transferOwnership()`: استبدال `Gate::authorize('transferOwnership', ...)` بـ`$this->authorizeGrantingOwnership($actor, $from->organization)` |
| `tests/Feature/Platform/PlatformAuthorizationHardeningTest.php` | 6 اختبارات جديدة (قسم 5) |
| `docs/marketplace-architecture-blueprint.md` | ✅ **مُنفَّذ بالفعل** — AD-017 مُسجَّلة |
| `docs/marketplace-implementation-specification.md` | ✅ **مُنفَّذ بالفعل** — AD-017 مُرحَّلة |
| `docs/platform-authorization-hardening-completion-report.md` | تحديث لاحق يوثّق إغلاق Finding H1 رسميًا |

**لا تغيير على:** `OrganizationPolicy.php`، `MembershipsRelationManager.php` (الزر نفسه لا يحتاج تعديل — يستدعي `transferOwnership()` أصلًا، الإصلاح داخلي بالكامل بالـService)، أي Migration، أي Filament Resource آخر.

**خطر Regression:** فحصت `MembershipServiceTest.php` بالكامل (16 اختبارًا موجودًا) — **لا اختبار حالي يستخدم فاعل Staff متوقِّعًا نجاح `transferOwnership()`** (كل الاختبارات الناجحة تستخدم `$owner` حقيقيًا، كل محاولات فاعل غير-Owner تتوقَّع الرفض أصلًا). **صفر خطر Regression متوقَّع** على الاختبارات الـ213 الحالية.

---

## 7. تأكيد صريح — أي مسار يبقى خارج القاعدة الموحَّدة؟

**بعد هذا الإصلاح: لا شيء.** الثلاث نقاط الكتابة الوحيدة على `Membership.role` بكامل الكود (قسم 1) ستكون جميعها خلف `authorizeGrantingOwnership()` عند منح/ترقية/نقل دور Owner تحديدًا:

```
add()              → role=Owner  → authorizeGrantingOwnership() ✅
changeRole()        → →Owner     → authorizeGrantingOwnership() ✅
transferOwnership() → →Owner دائمًا → authorizeGrantingOwnership() ✅ (بعد هذا الإصلاح)
```

**لا مسار رابع موجود اليوم** (مؤكَّد بفحص شامل، لا استنتاج). أي تابع جديد يُضاف مستقبلًا وينتج عنه `role=Owner` **يجب** يستدعي نفس التابع — هذا الالتزام مُسجَّل الآن كقيد معماري دائم (AD-017، قسم "الأثر الملزم").

---

## ملخص القرار المطلوب منك

1. الملفات والمسارات المتأثرة — قسم 6.
2. قاعدة Authorization النهائية — قسم 2 (سطر واحد، لا منطق جديد).
3. Security Matrix — قسم 3.
4. Attack Matrix — قسم 4.
5. الاختبارات الجديدة — قسم 5 (6 اختبارات).
6. أي مسار يبقى خارج القاعدة — قسم 7: **لا شيء**.

**بانتظار موافقتك الصريحة قبل كتابة أي كود.** لا Header/Dashboard/Marketplace/Phase OL — كما هو مؤكَّد بكل مرة سابقة.
