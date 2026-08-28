# Phase OL — Organization Lifecycle — Implementation Specification

**الحالة:** مواصفة تنفيذ، يليها كود فورًا بنفس التصريح (🟢 Phase OL فقط). **Phase OI مكتملة ومُعتمَدة — لا تُعاد فتحها.**
**المرجع:** `organization-lifecycle-hardening-design.md` (التصميم المعتمَد) · `owner-integrity-hardening-design.md` · `phase-oi-completion-report.md` · AD-016 (فجوة Audit للعضوية، غير مُغلَقة بهذي المرحلة أيضًا — تبقى مسجَّلة).

---

## القرارات المعتمَدة (مرجع سريع)

1. الحالات: `Active`/`Archived` فقط — لا `Deactivated`. **تمثيل صريح بعمود `status`** (قرار المستخدم المباشر: "استخدم حقل حالة صريح لا منطقًا مشتقًا").
2. Archive المسار التشغيلي الوحيد — **Hard Delete أُلغي نهائيًا من الـDomain**.
3. Archive يمر عبر Domain Service حصرًا — لا Filament CRUD مباشر.
4. Archive يُلغي الاشتراكات المؤسسية عبر `OrganizationSubscriptionService::cancel()` **الموجود والمُختبَر فعليًا** — لا منطق جديد مواز.
5. الوصول الفعلي يُحسَم عبر `EntitlementResolver` فقط — **صفر تعديل عليه**، صفر Query جديد من نوع "organization.archived".
6. Restore لا يُعيد تفعيل أي Subscription/Access تلقائيًا.
7. `OrganizationArchived`/`OrganizationRestored` **حدثان جديدان** بـ`AuditLog` (AD-001 مُعدَّل).
8. لا Header/Dashboard/Navigation/Marketplace UI. لا L3/L4. لا `owner_id` Schema. لا Hard Delete Workaround. لا تعديل AuditLog تاريخي. لا مسارات تنظيف خاصة للبيانات الصناعية الحالية.

---

## 1. Migration — عمود واحد فقط

```php
Schema::table('organizations', function (Blueprint $table) {
    $table->string('status')->default('active')->after('type');
});
```
لا تعديل آخر على الجدول. `active`/`archived` قيمتان فقط (إنفاذ بمستوى التطبيق، لا `ENUM` DB-level — يطابق نمط `subscriptions.status`/`app_subscriptions.status` الموجود أصلًا بكل المشروع).

---

## 2. `AuditEvent` — إضافة حدثين (AD-001 مُعدَّل)

```php
case OrganizationArchived = 'organization_archived';
case OrganizationRestored = 'organization_restored';
```
القائمة تصبح عشرة أحداث. **لا حدث ثالث يُضاف بهذي المرحلة** (تغيير الدور/العضوية يبقى بلا حدث — AD-016 غير مُغلَقة هنا، مؤكَّد صراحة بالمواصفة).

---

## 3. `OrganizationPolicy` — توابع جديدة

```php
archive(User $user, Organization $organization): bool   // Owner فقط
restore(User $user, Organization $organization): bool   // Owner فقط
```
يطابق منطق `transferOwnership`/`manageSubscription` (قرار بمسؤولية جسيمة على مستوى المؤسسة كاملة، لا فعل تشغيلي يومي) — Owner حصرًا، لا Admin.

---

## 4. `OrganizationLifecycleService` — التصميم

### `archive(User $actor, Organization $organization): void`
```
1. Gate::forUser($actor)->authorize('archive', $organization)
2. DB::transaction:
   a. قفل صف Organization (lockForUpdate)
   b. لو status == 'archived' بالفعل → لا فعل (Idempotent، لا خطأ)
   c. لكل Subscription نشط (marketplaceSubscriptions()->where('status','active')):
        OrganizationSubscriptionService::cancel($actor, $subscription)
        (الموجود فعليًا — يُبطِل كل Seat + كل AccessAssignment + يسجّل
        Audit خاص بكل واحد، بنفس المعاملة)
   d. organization.status = 'archived'
   e. AuditLog::create(OrganizationArchived, subject=organization, organization_id=organization.id, actor=actor)
```

### `restore(User $actor, Organization $organization): void`
```
1. Gate::forUser($actor)->authorize('restore', $organization)
2. DB::transaction:
   a. قفل صف Organization
   b. لو status == 'active' بالفعل → لا فعل (Idempotent)
   c. organization.status = 'active'
   d. AuditLog::create(OrganizationRestored, ...)
   (لا خطوة رابعة — لا لمس لأي Subscription/Seat/Access، القرار 6 أعلاه)
```

**لماذا لا فحص إضافي على الوصول بعد Archive:** القرار 5 — `EntitlementResolver` يفحص `subscription.status == active` أصلًا لكل قرار وصول مؤسسي (الخطوة 5 بمنطقه الحالي، بلا تعديل) — بما إن Archive يُبطِل كل اشتراك نشط فعليًا (خطوة 2.c أعلاه)، أي محاولة وصول لاحقة تُرفَض تلقائيًا بنفس المسار المُختبَر أصلًا منذ Phase 1b/2B، بلا أي كود إضافي.

---

## 5. Filament — إزالة Hard Delete، إضافة Archive/Restore

`OrganizationResource::table()`:
- **إزالة `DeleteAction`/`DeleteBulkAction`** بالكامل — Hard Delete أُلغي من الـDomain، لا يبقى مسار له حتى بالواجهة.
- فعل جديد **"أرشفة"** (مرئي لو `status=active`) → `OrganizationLifecycleService::archive()`.
- فعل جديد **"استعادة"** (مرئي لو `status=archived`) → `OrganizationLifecycleService::restore()`.
- عمود جديد بالجدول يعرض `status` (Badge: أخضر لـactive، رمادي لـarchived).
- نفس معالجة الأخطاء المُعتمَدة بـPhase OI (`Notification` + `Halt`، لا صفحة عطل خام).

`EditOrganization` (صفحة التعديل): إزالة `Actions\DeleteAction::make()` من `getHeaderActions()`، استبداله بفعل الأرشفة/الاستعادة المناسب حسب الحالة.

---

## 6. Testing Strategy — الأحد عشر بندًا المطلوبة

| # | البند | التصميم |
|---|---|---|
| 1 | Archive بمؤسسة لديها Subscription | يُلغى صراحة، `status=cancelled` |
| 2 | Archive بمؤسسة لديها Seats | تُحرَّر تلقائيًا (أثر جانبي لـ`cancel()`) |
| 3 | إبطال الوصول بعد Archive | `EntitlementResolver` يرفض فورًا (لا كود جديد، القرار 5) |
| 4 | لا إعادة وصول تلقائي بعد Restore | Subscription يبقى `cancelled` بعد Restore |
| 5 | Restore لمؤسسة Archived | `status` يعود `active`، لا شيء آخر يتغيّر |
| 6 | Archive بواسطة Admin/Member غير مخوَّل | `AuthorizationException` |
| 7 | محاولة الوصول لمؤسسة Archived | نفس بند 3، من زاوية "مستخدم له مقعد سابقًا" |
| 8 | لا Orphan Subscription | لا `Subscription(subscriber_type=organization, status=active)` لمؤسسة `archived` |
| 9 | Audit Events | `OrganizationArchived`/`Restored` مسجَّلان بـ`organization_id`/`actor_user_id` صحيحين |
| 10 | Concurrency | Archive مزدوج متزامن حقيقي لنفس المؤسسة (Idempotency تحت تزامن فعلي) |
| 11 | Regression | كامل 157 اختبار حالي يبقون كما هم |

**اكتشاف جانبي يستحق اختبارًا توثيقيًا (لا إصلاحًا — خارج نطاق OL):** `SeatService::assign()` لا يتحقق من `subscription.status == active` قبل تعيين مقعد — نظريًا يمكن تعيين مقعد جديد لاشتراك `cancelled` (ناتج Archive أو إلغاء عادي). **لا خطر وصول فعلي** (`EntitlementResolver` يرفض بصرف النظر، القرار 5 يحمي هنا أيضًا بالصدفة الإيجابية) — لكنه يستحق توثيقًا صريحًا باختبار مستقل يثبت الحالة كما هي اليوم، لا إصلاحًا (يخالف "لا تحوّل OL لمشروع ضخم").

---

## 7. لا نلمس

Header/Dashboard/Navigation/Marketplace UI — صفر. `EntitlementResolver` — صفر تعديل. `owner_id` Schema — صفر. `AuditLog` تاريخي — صفر تعديل/حذف. L3/L4 — صفر بدء. بيانات Synthetic الحالية (org 5/user 16/audit_log 36) — صفر لمس، صفر مسار تنظيف خاص.

---

**بانتظار — لا شيء، تصريح مباشر بالتنفيذ بنفس رسالتك. الكود يبدأ فورًا.**
