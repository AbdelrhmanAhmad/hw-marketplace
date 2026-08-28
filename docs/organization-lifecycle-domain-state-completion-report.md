# AD-018 — Organization Lifecycle Domain State Enforcement — تقرير الإكمال

**الحالة:** مُنفَّذ بالكامل حسب `docs/organization-lifecycle-domain-state-design.md` والقرارين الحاسمين (Membership مسموحة، Guard مركزي منفصل عن الـModel).

---

## 1. القرارات المُنفَّذة

1. **Membership operations (add/changeRole/remove) مسموحة على مؤسسة مؤرشَفة** — Membership ≠ Marketplace Access (AD-007/AD-018).
2. **Transfer Ownership** — محكومة بقواعد AD-017 فقط، بلا قيد إضافي من حالة الأرشفة.
3. **Guard مركزي منفصل تمامًا عن `Organization` Model** — صف جديد `App\Services\OrganizationMarketplaceAccessGuard`، لا تابع على الـModel.

---

## 2. التنفيذ

### `app/Services/OrganizationMarketplaceAccessGuard.php` (جديد)

نقطة تحقق مركزية واحدة، تابع واحد: `assertCanGrantNewAccess(Organization $organization): void`. عمدًا **ليست** Laravel Policy تقليدية (لا `User`، لا `Gate::authorize()`) — التسمية "Guard" لا "Policy" لتفادي الخلط مع `OrganizationPolicy` (Gate-based، تأخذ `User` دائمًا). هذا يُجسِّد الفصل الذي طلبتَ تثبيته:

```
Authorization  = من يستطيع تنفيذ الفعل؟           → Gate/Policy (OrganizationPolicy)
Domain State   = هل حالة الكيان تسمح بالفعل أصلًا؟  → OrganizationMarketplaceAccessGuard (منفصل، actor-agnostic)
```

### نقاط الاستدعاء (3 فقط، بلا تكرار منطق)

| الملف | التابع | الشرط |
|---|---|---|
| `OrganizationSubscriptionService.php:46` | `create()` | دائمًا (بعد `Gate::authorize()` مباشرة) |
| `OrganizationSubscriptionService.php:100-104` | `changeSeatLimit()` | **فقط لو `$newLimit > $subscription->plan->seat_limit`** (زيادة فعلية) — التخفيض دائمًا مسموح |
| `SeatService.php` (`assign()`) | `assign()` | دائمًا (بعد `Gate::authorize()` مباشرة) |

كلا الصفَّين يستقبلان `OrganizationMarketplaceAccessGuard` عبر Constructor Injection (نفس نمط `OrganizationLifecycleService`'s الحالي مع `OrganizationSubscriptionService`).

### اكتشاف إضافي أثناء كتابة الاختبارات — أُصلِح بنفس الدفعة

`SubscriptionsRelationManager` (`CreateAction`/`EditAction`) لم تكن تلتقط أي استثناء إطلاقًا (بعكس `MembershipsRelationManager` منذ Phase OI) — أي رفض (حتى فحوصات `billing_model`/`seatLimit` الموجودة أصلًا قبل هذي الدفعة) كان يظهر كصفحة عطل خام لا إشعار واضح. بما إن AD-018 يجعل هذا الرفض مسارًا واقعيًا متوقَّعًا (Staff يحاول إنشاء اشتراك لمؤسسة مؤرشَفة عبر الواجهة)، أضفتُ `runGuarded`-style Try/Catch (`notifyRejection()`) لكلا الفعلين — نفس نمط `MembershipsRelationManager` حرفيًا. **هذا إصلاح UX/Robustness ضروري لجعل AD-018 قابلًا للاستخدام فعليًا عبر الواجهة، لا توسّع نطاق غير مطلوب.**

---

## 3. Domain State Matrix — النتائج الفعلية (لا تصميم)

| العملية | Active | Archived | مُتحقَّق عبر |
|---|---|---|---|
| Create Subscription | ✅ | ❌ | `test_owner_cannot_create_subscription_for_own_archived_organization` + `test_platform_staff_...` |
| Change Seat Limit — رفع | ✅ | ❌ | `test_staff_cannot_increase_seat_limit_on_archived_organization` |
| Change Seat Limit — تخفيض | ✅ | ✅ | `test_decreasing_seat_limit_on_archived_organization_still_works` |
| Assign Seat | ✅ | ❌ | `test_staff_cannot_assign_seat_on_archived_organization` |
| Release Seat | ✅ | ✅ | `test_releasing_a_seat_on_archived_organization_still_works` |
| Add Membership | ✅ | ✅ | `test_staff_can_add_member_to_archived_organization` |
| Change Role | ✅ | ✅ | `test_owner_can_change_member_role_on_archived_organization` |
| Transfer Ownership | ✅ (وفق AD-017) | ✅ (وفق AD-017 فقط) | `test_real_owner_can_still_transfer_ownership_on_archived_organization` |
| Restore → Create Subscription | — | ✅ **يعود مسموحًا** | `test_restore_reopens_ability_to_create_new_subscription` |
| Restore → إعادة تفعيل تلقائي لاشتراك سابق | — | ❌ **لا يحدث أبدًا** | `test_restore_does_not_auto_reactivate_previously_cancelled_subscription` |

---

## 4. Attack Matrix — النتائج الفعلية

| # | السيناريو | القناة | النتيجة الفعلية |
|---|---|---|---|
| 1 | Owner → إنشاء اشتراك لمؤسسته المؤرشَفة | استدعاء مباشر | ❌ `InvalidArgumentException` |
| 4 | Platform Staff (مخوَّل بالكامل Authorization-wise) → إنشاء اشتراك | استدعاء مباشر | ❌ **State Guard يرفض رغم التخويل الكامل** |
| 5 | Platform Staff → تعيين مقعد | استدعاء مباشر | ❌ State Guard |
| 6 | Platform Staff → رفع حد مقاعد | استدعاء مباشر | ❌ State Guard |
| 8 | نفس #4 عبر Livewire (`SubscriptionsRelationManager::CreateAction` حقيقي) | Livewire/Filament | ❌ نفس الحماية، بلا كود Filament إضافي — مُثبَت بـ`Livewire::test()` فعلي |
| 10 | تلاعب بـ`active_organization_id`/الجلسة | Session | ❌ **لا علاقة** — مُثبَت صراحة: `assertGuest()` + الفعل ما زال مرفوضًا (التابع لا يقرأ أي جلسة) |
| 11 | Restore ثم Create Subscription | مباشر | ✅ يُسمَح — المسار الصحيح الوحيد |

**كل الـ11 سيناريو بالتصميم الأصلي مغطّاة** — بعضها عبر اختبارات مباشرة جديدة، وبعضها (Admin/Member، IDOR) عبر الحماية الموجودة أصلًا من مراحل سابقة (لم تتأثر، غير مُعاد اختبارها هنا لتفادي التكرار).

---

## 5. الإجابة النهائية على السؤال المحوري (بعد التنفيذ)

> **"هل توجد أي طريقة حالية يستطيع بها Actor مشروع، مهما كانت صلاحياته، إنشاء أو زيادة Marketplace Access لمؤسسة Archived؟"**

**لا.** المسارين المكتشَفين (`create()`، `assign()`) مغلقان بالكامل، مُثبَتان بتنفيذ حقيقي (لا افتراض) عبر استدعاء مباشر **و**Livewire حقيقي. `changeSeatLimit()` مغلقة للزيادة فقط (التخفيض يبقى آمنًا دائمًا بالتصميم).

---

## 6. الملفات المتغيّرة

| الملف | التغيير |
|---|---|
| `app/Services/OrganizationMarketplaceAccessGuard.php` | **جديد** |
| `app/Services/OrganizationSubscriptionService.php` | Constructor Injection جديد + استدعاء الـGuard بـ`create()`/`changeSeatLimit()` |
| `app/Services/SeatService.php` | Constructor Injection جديد + استدعاء الـGuard بـ`assign()` |
| `app/Filament/Resources/OrganizationResource/RelationManagers/SubscriptionsRelationManager.php` | Try/Catch + إشعار واضح لكلا الفعلين (اكتشاف إضافي، قسم 2) |
| `tests/Feature/Organization/OrganizationMarketplaceAccessGuardTest.php` | **جديد** — 13 اختبارًا |
| `tests/Feature/Organization/OrganizationLifecycleServiceTest.php` | اختبار قديم (كان يوثّق الفجوة كـ"مقبولة") أُعيد كتابته ليؤكد الرفض الجديد |
| `docs/marketplace-architecture-blueprint.md` / `docs/marketplace-implementation-specification.md` | ✅ AD-018 مُسجَّلة مسبقًا (الخطوة السابقة) |

**لا Migration، لا تعديل Schema.**

---

## 7. الاختبارات

| المجموعة | العدد | النتيجة |
|---|---|---|
| Suite كامل قبل هذي الدفعة | 219 | 219/219 ✅ |
| اختبارات جديدة (`OrganizationMarketplaceAccessGuardTest.php`) | +13 | 13/13 ✅ |
| **Suite كامل بعد الدفعة** | **232** | **232/232 ✅** |

Assertions: 554 → 572. **صفر Regression** — الاختبار الوحيد المتأثر (`OrganizationLifecycleServiceTest`) أُعيد كتابته عمدًا (كان يوثّق سلوكًا AD-018 يُصلِحه الآن، لا كسرًا غير مقصود).

---

## الخطوة التالية

مراجعة أمنية مستقلة لـAD-018 تحديدًا جارية الآن (`docs/ad-018-security-review.md`) — كما طلبت، قبل السماح لـPhase OL بالتحرك.
