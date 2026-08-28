# AD-018 — إصلاح Race Condition (Finding AD018-1) — تقرير الإكمال

**الحالة:** مُنفَّذ حسب طلبك بالضبط — إصلاح ضيق النطاق، لا توسّع لـAD-018، لا لمس لـMembership/Ownership/UI.

---

## 1. التغيير الفعلي

**ملف واحد، دالة واحدة:** `app/Services/OrganizationSubscriptionService.php::create()`.

```diff
  return DB::transaction(function () use ($actor, $organization, $item, $planName, $seatLimit) {
-     $existing = $organization->marketplaceSubscriptions()
+     $locked = Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
+     $this->accessGuard->assertCanGrantNewAccess($locked);
+
+     $existing = $locked->marketplaceSubscriptions()
          ->where('marketplace_item_id', $item->id)
          ->first();
      ...
-     $organization->marketplaceSubscriptions()->save($subscription);
+     $locked->marketplaceSubscriptions()->save($subscription);
```

**استدعاء الـGuard انتقل من قبل المعاملة (على `$organization` المُمرَّر، قد يكون قديمًا) إلى داخل المعاملة، بعد `lockForUpdate()` مباشرة (على `$locked`، الصف الطازج المقفول)** — بالضبط نفس نمط `OrganizationLifecycleService::archive()` حرفيًا (`Organization::whereKey(...)->lockForUpdate()->firstOrFail()` كأول سطر داخل المعاملة).

**لم يتغيّر:** `OrganizationMarketplaceAccessGuard` نفسه (لا حاجة — يستقبل `Organization` كما هو، سواء كان الـinstance قديمًا أو طازجًا)، `changeSeatLimit()`، `assign()`، `MembershipService`، أي Filament Resource، أي Migration.

---

## 2. لماذا هذا يغلق الفجوة النظرية

**قبل الإصلاح:** فحص الحالة يحدث على `$organization` كما استلمه المستدعي — لو استُلم **قبل** أرشفة متزامنة، الفحص يرى "Active" رغم إن الصف الحقيقي بقاعدة البيانات أصبح "Archived" فعليًا بلحظة الفحص.

**بعد الإصلاح:** الفحص يحدث **بعد** `lockForUpdate()` — لو `archive()` متزامنة تملك القفل فعلًا، `create()` تنتظر (Blocking، سلوك قياسي لـRow-Level Locking) حتى تُتمّ `archive()` معاملتها وتُحرِّر القفل، **ثم** `create()` تقرأ الصف **الطازج فعليًا** (بعد الأرشفة) — فيُرفَض بشكل صحيح. لا نافذة زمنية متبقية بين "من يقفل أولًا" و"من يفحص الحالة".

---

## 3. الاختبارات

### اختبار Regression جديد — يثبت ترتيب القفل، لا Concurrency حقيقيًا (توضيح صريح كما طلبتَ)

`tests/Feature/Organization/OrganizationMarketplaceAccessGuardTest.php::test_create_rechecks_archived_status_on_locked_row_even_with_a_stale_organization_instance`:

```php
$staleOrganization = Organization::find($organization->id); // نسخة منفصلة، "active" بالذاكرة
app(OrganizationLifecycleService::class)->archive($owner, Organization::find($organization->id)); // أرشفة عبر نسخة أخرى
// $staleOrganization->status لا يزال 'active' بالذاكرة (مؤكَّد بالاختبار)
$this->expectException(InvalidArgumentException::class);
app(OrganizationSubscriptionService::class)->create($owner, $staleOrganization, $item, 'Professional', 5); // يُرفَض رغم الـinstance القديمة
```

**ما يثبته هذا الاختبار فعليًا:** أن `create()` **لا تثق** بحالة الـinstance المُمرَّر — تعيد القراءة من الصف المقفول دائمًا. هذا يحاكي **بنية** سيناريو الـRace (فاعل يحمل معلومة قديمة) **بمعزل عن التزامن الحقيقي**.

**ما لا يثبته (بصراحة كاملة، كما طلبتَ):** لا يثبت سلوك Row-Level Locking الحقيقي تحت تزامن فعلي بمحرك MySQL/Postgres — **غير قابل للإثبات بالبيئة الحالية** (SQLite Single-Writer، تُسلسِل كل الكتابة تلقائيًا، فلا Race يمكن ملاحظته إطلاقًا بها مهما حاولنا). هذا نفس القيد المذكور بالمراجعة السابقة (`docs/ad-018-security-review.md`)، لم يتغيّر — الإصلاح يعتمد على **صحة منطقية قياسية** لـLock-Then-Check (نمط `archive()` نفسه، مُختبَر ومُعتمَد سابقًا)، لا على إثبات تجريبي لظرف لا يمكن إعادة إنتاجه بأداة الاختبار المتاحة.

### نتائج التشغيل الكامل

| | قبل هذا الإصلاح | بعد |
|---|---|---|
| الاختبارات | 232 | **233** |
| Assertions | 572 | 574 |
| النتيجة | 232/232 ✅ | **233/233 ✅** |

**صفر Regression.**

---

## 4. إعادة تشغيل Attack Matrix الخاصة بـAD-018 (تأكيد كامل بعد الإصلاح)

| السيناريو | النتيجة |
|---|---|
| Create Subscription على مؤسسة مؤرشَفة (Owner) | ❌ مرفوض |
| Create Subscription على مؤسسة مؤرشَفة (Platform Staff، مخوَّل بالكامل) | ❌ مرفوض |
| Create Subscription — نفس السيناريو بـinstance قديمة (محاكاة الـRace) | ❌ **مرفوض الآن (الإصلاح الجديد)** |
| Assign Seat على مؤسسة مؤرشَفة | ❌ مرفوض (بلا تغيير) |
| Seat Limit Increase على مؤسسة مؤرشَفة | ❌ مرفوض (بلا تغيير) |
| Seat Limit Decrease على مؤسسة مؤرشَفة | ✅ يعمل دائمًا (بلا تغيير) |
| Membership — إضافة/تغيير Role على مؤسسة مؤرشَفة | ✅ يعمل (بلا تغيير — مؤكَّد أن `MembershipService` **لا يستدعي الـGuard إطلاقًا**، `grep` مباشر) |
| Restore ثم Create Subscription | ✅ يعمل (بلا تغيير) |
| Livewire (`SubscriptionsRelationManager::CreateAction`) على مؤسسة مؤرشَفة | ❌ مرفوض، إشعار واضح (بلا تغيير) |

**لا مسار خامس ظهر** — Inventory نهائي مُعاد فحصه: `accessGuard->assertCanGrantNewAccess` لا تزال 3 نقاط استدعاء بالضبط (`create()` الآن على `$locked`، `changeSeatLimit()`، `assign()`)، `MembershipService` صفر إشارة.

---

## 5. ما لم يتغيّر (تأكيد صريح حسب قيودك)

- ❌ لا تعديل على `OrganizationMarketplaceAccessGuard` نفسه.
- ❌ لا نقل الـGuard لأي Model.
- ❌ لا تعديل على `changeSeatLimit()`/`assign()` (نفس نمط القفل **لم يُطبَّق عليهما** — خارج الطلب الصريح؛ أذكره كملاحظة تناظرية فقط، لا توصية فعل الآن).
- ❌ لا تعديل على Membership/Ownership/Header/Dashboard/Marketplace UI.
- ❌ لا فتح L1/L2.
- ❌ لا توسّع نطاق AD-018.

---

## الخطوة التالية

مراجعة أمنية مستقلة صغيرة لـAD-018 (بعد هذا الإصلاح تحديدًا) جارية الآن (`docs/ad-018-security-review-2.md`). **لا Phase OL حتى اعتمادك.**
