# Phase OI — Owner Integrity — Implementation Specification

**الحالة:** مواصفة تنفيذ، يليها كود فورًا بنفس التصريح (🟢 Phase OI فقط). **Phase OL (Organization Lifecycle) مؤجَّلة بالكامل — لا تُدمَج، لا تُلمَس.**
**المرجع:** `owner-integrity-hardening-design.md` (كل قرار هنا ينفّذ قرارًا مُعتمَدًا هناك حرفيًا، لا قرار جديد يُخترَع).

---

## القرارات المعتمَدة التي يُبنى عليها هذا التنفيذ (مرجع سريع، لا إعادة نقاش)

1. `Membership.role=Owner` مصدر الحقيقة الوحيد — لا Synchronization مع `owner_id`.
2. Multiple Owners مسموحة — لا قيد Cardinality.
3. Last Owner Rule إلزامية — حذف/تخفيض آخر Owner ممنوع **إلا** ضمن Transfer Ownership ذرية.
4. لا إصلاح تلقائي لتضارب `owner_id` الحالي (Org 1/Org 2).
5. `owner_id` يدخل Retirement تدريجي — هذي المرحلة تُنفِّذ **المرحلتين 1+2 فقط** من خطة الخمس مراحل (تنبيه بصري + تجميد الكتابة اليدوية) — **لا حذف عمود، لا Migration Schema**.

---

## نطاق Phase OI (حصري، لا تجاوز)

**داخل النطاق:**
- `MembershipService` جديد: `changeRole()`, `remove()`, `transferOwnership()`.
- Last Owner Rule مُنفَّذة داخل الـService، مضمونة بمعاملة DB + قفل صف صريح (لا اعتماد على انضباط الاستدعاء).
- `OrganizationPolicy`: توابع جديدة `manageMembers()` (Owner أو Admin)، `transferOwnership()` (Owner فقط).
- `MembershipsRelationManager` (Filament): كل فعل (Edit/Delete/الفعل الجديد "نقل الملكية") يمر عبر `MembershipService` حصرًا — صفر `Model::update()`/`delete()` مباشر.
- `OrganizationResource` (Filament): حقل `owner_id` يتحوّل لعرض فقط (Stage 2 من خطة Retirement) + مؤشِّر بصري لو لا يطابق أي Membership فعلي (Stage 1).
- اختبارات: Unit/Feature لكل قاعدة، اختبار Authorization مستقل عن أي واجهة، اختبار Concurrency حقيقي.

**خارج النطاق صراحة (لا يُلمَس بهذي المرحلة):**
- ❌ Phase OL بالكامل (Active/Archived، Archive Service، Audit Events الجديدة `OrganizationArchived`/`OrganizationRestored`).
- ❌ حذف عمود `owner_id` أو أي Migration Schema.
- ❌ Header/Dashboard/Navigation/Marketplace UI.
- ❌ Hard Delete (أُلغي نهائيًا من الـDomain بقرار سابق، لا علاقة لهذي المرحلة به أصلًا).
- ❌ Audit Event جديد لتغيير الدور/نقل الملكية — **لم يُطلَب صراحة بنطاق Phase OI** (بعكس `OrganizationArchived`/`Restored` المطلوبة لـPhase OL تحديدًا) — يبقى هذا فجوة موثَّقة مسبقًا (`phase-2c-organization-access-lifecycle-design.md`)، **غير مُغلَقة بهذي المرحلة، تُذكَر صراحة بالتقرير الختامي لا تُخفى**.

---

## 1. `MembershipService` — التصميم التفصيلي

### `changeRole(User $actor, Membership $membership, MembershipRole $newRole): void`
```
1. Gate::forUser($actor)->authorize('manageMembers', $membership->organization)
   (يرمي AuthorizationException تلقائيًا لو رُفِض — لا اعتماد على تحقق خارجي)
2. DB::transaction:
   a. قفل صف Organization (lockForUpdate) — يُسلسِل كل تعديلات هذي المؤسسة معًا
   b. قفل صف Membership المستهدَف (lockForUpdate) — يقرأ الحالة الحقيقية اللحظية
   c. لو (current.role == Owner) AND (newRole != Owner):
        عدّ Membership.role=Owner لنفس المؤسسة (داخل نفس القفل)
        لو العدّ ≤ 1 → InvalidArgumentException("آخر Owner — استخدم نقل الملكية")
   d. تحديث الدور
```

### `remove(User $actor, Membership $membership): void`
```
1. Gate::forUser($actor)->authorize('manageMembers', $membership->organization)
2. DB::transaction:
   a. قفل Organization ثم Membership (نفس الترتيب أعلاه)
   b. لو current.role == Owner: نفس فحص العدّ ≤ 1 → رفض
   c. Membership::delete() — Eloquent حقيقي (يُطلِق MembershipRevoked Event
      كالمعتاد، بعكس Cascade — لا تغيير على هذا السلوك الموجود أصلًا)
```

### `transferOwnership(User $actor, Membership $from, Membership $to, MembershipRole $demoteFromTo = Admin): void`
```
1. تحقق: from.organization_id == to.organization_id (وإلا رفض)
2. تحقق: demoteFromTo != Owner (نقل بلا تغيير فعلي لا معنى له)
3. Gate::forUser($actor)->authorize('transferOwnership', $from->organization)
4. DB::transaction:
   a. قفل Organization
   b. قفل from وto (lockForUpdate لكل منهما)
   c. to.role = Owner
   d. from.role = demoteFromTo
   (لا فحص "آخر Owner" هنا — العملية ذاتها مضمونة الأمان بنيويًا: أي لحظة
   بالمعاملة يوجد فيها إما Owner قديم أو جديد أو كلاهما، أبدًا صفر)
```

**لماذا قفل Organization أولًا بكل التوابع الثلاثة:** يمنع سباقًا بين عمليتين مستقلتين على مؤسسة واحدة (مثال: عضوان يحاولان التخفيض/الحذف بنفس اللحظة بمؤسسة لها بالضبط Owner واحد إضافي) — التسلسل الإجباري عبر القفل يضمن كل عملية ترى الحالة الحقيقية بعد اكتمال أي عملية سابقة، لا حالة قديمة (Stale Read). **نفس مبدأ AD-003 (قفل الأب، تحقق الابن) المُطبَّق سابقًا بـ`SeatService`.**

---

## 2. `OrganizationPolicy` — التوابع الجديدة

```php
manageMembers(User $user, Organization $organization): bool
    // Owner أو Admin — يطابق منطق manageSeats (BR-2B-02، فعل تشغيلي يومي)

transferOwnership(User $user, Organization $organization): bool
    // Owner فقط — يطابق منطق manageSubscription (BR-2B-01، قرار بمسؤولية جسيمة)
```

**لا تعديل على `manageSubscription`/`manageSeats` الموجودتين — إضافة بحتة.**

---

## 3. Filament — منع أي CRUD مباشر يتجاوز الـService

`MembershipsRelationManager`:
- `EditAction::using()` → يستدعي `MembershipService::changeRole()`. أي `InvalidArgumentException`/`AuthorizationException` يُحوَّل لإشعار Filament واضح (`Halt` + `Notification::danger()`) — لا صفحة عطل خام.
- `DeleteAction`/`DeleteBulkAction::using()` → نفس المبدأ، يستدعي `MembershipService::remove()`.
- **فعل جديد "نقل الملكية"** (مرئي فقط لصفوف `role=Owner`): نموذج يختار عضوًا آخر بنفس المؤسسة + الدور الجديد لصاحب الملكية القديم (افتراضي: Admin) → يستدعي `MembershipService::transferOwnership()`.
- `CreateAction` (إضافة عضو جديد): **لا تغيير** — خارج نطاق Last Owner Rule (إضافة لا تُنقِص عدد الـOwners أبدًا)، خارج نطاق Phase OI صراحة.

**تحقق Authorization على مستوى الفعل نفسه بـFilament (`->authorize()`) بالإضافة لتحقق الـService الداخلي — طبقتان مستقلتان، لا اعتماد على واحدة فقط (نفس نمط AD-012).**

---

## 4. `owner_id` — تنفيذ المرحلتين 1+2 من خطة Retirement

- **Stage 2 (تجميد الكتابة):** حقل `owner_id` بنموذج Create/Edit يصبح `->disabled()` — لا كتابة يدوية جديدة ممكنة بعد الآن (القيم الحالية، شاملة المتضاربة، تبقى كما هي بلا تعديل — القرار 4 أعلاه).
- **Stage 1 (تنبيه بصري):** عمود/ملاحظة جديدة بجدول قائمة المؤسسات تُظهِر بوضوح لو `owner_id` **لا** يطابق أي `Membership.role=Owner` حالي — قراءة فقط، لا تمنع أي شيء.

---

## 5. Testing Strategy

| الفئة | الاختبارات |
|---|---|
| **Unit — Last Owner Rule** | رفض `changeRole` لآخر Owner · رفض `remove` لآخر Owner · قبول كلاهما لو يوجد Owner آخر |
| **Unit — Transfer Ownership** | نجاح كامل (to=Owner, from=demoteFromTo) · رفض لو منظمتان مختلفتان · رفض لو demoteFromTo=Owner |
| **Feature — Authorization (Backend، لا UI)** | `changeRole`/`remove`/`transferOwnership` تُلقي `AuthorizationException` لعضو ليس Owner/Admin (أو ليس Owner لـtransfer تحديدًا) — تُختبَر مباشرة عبر استدعاء الـService، **بلا أي HTTP/Filament Layer** |
| **Regression** | 141 اختبار حالي يبقون 141/141 — صفر كسر، خصوصًا `MembershipRevokedSeatCleanupTest` (تعتمد على `remove()` الجديد تُطلِق نفس الحدث) |
| **Concurrency (حقيقي، لا محاكاة)** | مؤسسة بـOwner اثنين، محاولتا `remove`/`changeRole` متزامنتان فعليًا (عمليتا OS منفصلتان) تستهدفان Owner مختلفَين — يجب واحدة فقط تنجح، النتيجة النهائية Owner واحد بالضبط، أبدًا صفر |

---

## 6. لا نلمس

Header/Dashboard/Navigation/Marketplace UI/بوابة معرفة — صفر. `owner_id` Schema — صفر تعديل. Hard Delete/Phase OL — صفر بدء.

---

**بانتظار — لا شيء، هذا تصريح مباشر بالتنفيذ (🟢) بنفس رسالتك. الكود يبدأ فورًا بعد هذي الوثيقة.**
