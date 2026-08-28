# Marketplace Access Control Audit — Phases 1a + 1b + 2A + 2B

**الحالة:** تدقيق فقط — بدون كود، بدون Migration، بدون أي تعديل. **لا إصلاح لأي فجوة مكتشَفة هنا — توثيق فقط، القرار بيدك.**
**المنهجية:** لا اعتماد على الذاكرة — كل نتيجة هنا مبنية على قراءة فعلية للكود الحالي (`grep` شامل + قراءة مباشرة للملفات الحرجة) وقت كتابة هذي الوثيقة.
**السؤال المحوري الذي طلبته:** هل لدينا "Access Resolver" واحد، أم منطق مبعثر؟ **الجواب المختصر: لا، ثلاث آليات مستقلة ومقصودة (Section 9)، ووُجِد تكرار منطقي حقيقي واحد غير مقصود بينها (Section 9.1).**

---

## 1. Access Matrix — كل مصادر الوصول الحقيقية اليوم

| المصدر | الآلية | من يستهلكه | حقيقي اليوم؟ |
|---|---|---|---|
| Personal Subscription (Phase 1b) | `Subscription(subscriber_type=user)` → `AccessAssignment` → `EntitlementResolver` خطوات ١-٤ | `MarketplaceController`, `MyAppsController` | ✅ نعم |
| Organization Subscription + Seat (Phase 2B) | `Subscription(subscriber_type=organization)` → `SubscriptionSeat` → `AccessAssignment` → `EntitlementResolver` خطوة ٥ | نفس أعلاه، بشرط `ActiveOrganizationContext` | ✅ نعم |
| Free Application (تلقائي) | `App\Support\FreeAppProvisioner` → **الجدول القديم** `app_subscriptions` (Legacy) | `DashboardController`, `HomeController`, `RegisteredUserController` **فقط** — **لا علاقة له بـ`EntitlementResolver` إطلاقًا** | ⚠️ نعم، لكن نظام منفصل تمامًا (راجع Section 10) |
| `AccessAssignment` مباشرة | راجع الصفين أعلاه — لا مسار وصول مستقل بمعزل عن Subscription | — | — |
| Direct URL (بوابة معرفة نفسها) | لا فحص وصول إطلاقًا — محتوى عام (AD-007) | زائر/مستخدم مباشرة | ✅ بالتصميم، ليس ثغرة |
| Direct URL (شاشات Marketplace: `/marketplace/{key}`) | نفس `EntitlementResolver` يُعاد استدعاؤه بكل طلب — لا Session Caching للقرار | `MarketplaceController::show` | ✅ آمن |
| Legacy `app_subscriptions` (قراءة مباشرة) | `User::hasActiveSubscription()`, `User::subscriptions()` | `DashboardController` فقط | ⚠️ معزول لكن حي (راجع Section 5) |
| Filament (`AppSubscriptionResource`) | CRUD مباشر على `app_subscriptions` القديم، بلا Policy تقيّد من يقدر يعدّل | أي مستخدم Filament مصادَق | ⚠️ فجوة موروثة من Phase 1a، خارج نطاق Marketplace (راجع Section 3) |

**خلاصة القسم:** لا يوجد مصدر وصول "خفي" غير موثَّق أعلاه — كل مسار مفحوص بـ`grep` شامل لاستدعاءات `EntitlementResolver`, `AccessAssignment::`, `hasActiveSubscription`, `marketplaceSubscriptions()`.

---

## 2. أولوية مصادر الوصول (Personal مقابل Organization)

**القاعدة الموثَّقة فعليًا بالكود (`EntitlementResolver::resolve()`):** الفحص الشخصي (خطوات ١-٤) يحدث **أولًا** ويُرجِع فورًا لو وُجد وصول فعّال — الفحص المؤسسي (خطوة ٥) **لا يُنفَّذ إطلاقًا** لو الشخصي كافٍ. هذا **اتحاد (Union) بأولوية الشخصي**، لا تنافس بين الاثنين.

**هل إلغاء أحدهما يؤثر على الآخر؟ لا — مُختبَر صراحة:**
- `test_cancelling_personal_subscription_does_not_affect_organization_access` (`OrganizationAccessFlowTest`)
- `test_organization_subscription_cancellation_does_not_affect_personal_subscription` (نفس الملف)

**السبب البنيوي (لا افتراض):** الصفّان صفَّان منفصلان تمامًا بجدول `subscriptions` (`subscriber_type` مختلف)، بلا أي مفتاح أجنبي بينهما. إلغاء أحدهما فعل `UPDATE` على صف واحد فقط.

---

## 3. Organization Isolation — مراجعة طبقة بطبقة

| الطبقة | الحالة | الدليل |
|---|---|---|
| **Organization IDs** | ✅ سليمة | كل استعلام مؤسسي يمرّر `subscriber_id`/`organization_id` صراحة من المسار (Route Model Binding)، لا من السياق النشط وحده (AD-012) |
| **Memberships** | ✅ سليمة | `OrganizationPolicy` تستعلم `Membership` مباشرة بقاعدة البيانات بكل استدعاء، لا تخزين مؤقت |
| **Subscriptions** | ✅ سليمة | `OrganizationSeatController::ensureSubscriptionBelongsToOrganization()` يتحقق صراحة إن `subscription.subscriber_id === organization.id` قبل أي فعل |
| **Seats** | ✅ سليمة | نفس التحقق أعلاه يغطي `seat->subscription` قبل السماح بالسحب |
| **AccessAssignments** | ✅ سليمة | تُقرأ فقط عبر `subscription->accessAssignments()`، لا استعلام مستقل بمعرّف مباشر بأي مكان |
| **Filament** | ⚠️ فجوة موروثة (ليست جديدة بـPhase 2B) | `SubscriptionsRelationManager` مُقيَّد صح بـ`getOwnerRecord()` — لكن **لا Policy على مستوى Filament نفسه تحدد أي موظف حكم ورقم يقدر يدير أي مؤسسة** — الثقة الحالية: كل مستخدم Filament مصادَق = موثوق بالكامل (قرار قائم منذ Phase 1a، لم يتغيّر، خارج نطاق تدقيق Marketplace تحديدًا لأنه يخص طبقة الموظفين لا العملاء) |
| **Routes** | ✅ سليمة | كل Route حسّاس داخل `middleware('auth')`، لا Route عام يكشف بيانات مؤسسة |
| **Controllers** | ✅ سليمة | `OrganizationSeatController` يعيد التحقق بكل تابع (لا يعتمد على تابع سابق بنفس الطلب) |
| **Policies** | ✅ سليمة | `OrganizationPolicy` الكيان الوحيد الذي يقرر الصلاحية الإدارية، لا منطق موازٍ بأي Controller |
| **Services** | ✅ سليمة | `SeatService::assign()` يعيد التحقق من العضوية الفعلية للمستخدم **المستهدَف** (لا الفاعل فقط) — طبقة دفاع مستقلة عن Controller |

**لم يُكتشف أي مسار فعلي لتسرّب بيانات بين مؤسستين بأي طبقة.**

---

## 4. Authorization vs Entitlement — هل الفصل مُطبَّق فعليًا بكل مكان؟

| الاستهلاك | Entitlement أم Authorization؟ | مطابق لـAD-005؟ |
|---|---|---|
| `EntitlementResolver::resolve()` | Entitlement بحت — "يقدر يفتح التطبيق؟" | ✅ |
| `OrganizationPolicy::manageSubscription/manageSeats` | Authorization إدارية (على المؤسسة نفسها، لا على تطبيق داخلي) | ✅ — لا تُستخدَم أبدًا لقرار "يقدر يستخدم Marketplace Item" |
| أي تطبيق مستهلَك (بوابة معرفة) | لا يوجد نظام Authorization داخلي فعليًا بعد (بوابة معرفة بلا صلاحيات فرق داخلية) | ✅ لا مخالفة — ببساطة غير مبني بعد، لا خلط حاصل لأنه لا وجود له |

**لم يُعثَر على أي سطر كود يستخدم خرج `EntitlementResolver` (`AccessDecision->allowed`) كمُدخَل لقرار "ماذا يقدر يفعل داخل تطبيق" — الفصل قائم بنيويًا لأن لا تطبيق يملك Authorization داخلي بعد أصلًا ليُخلَط بها.**

---

## 5. Legacy Isolation — `app_subscriptions`

**فحص شامل (`grep -rln "AppSubscription"`) — النتيجة الكاملة:**
```
app/Models/User.php                              → subscriptions()/hasActiveSubscription() القديمتان (Phase 1، لم تُحذَف عمدًا)
app/Models/AppSubscription.php                   → الـModel نفسه
app/Filament/Resources/AppSubscriptionResource*  → CRUD إداري قديم (Phase 1a)
app/Support/FreeAppProvisioner.php               → يكتب للجدول القديم فقط
```
**لا ملف واحد من Phase 1b/2A/2B يظهر بهذي القائمة.** `MarketplaceBackfillFreeAccess` (أمر الترحيل) يقرأ من الجدول القديم (عبر `User::subscriptions()`) **كمصدر ترحيل لمرة واحدة فقط** — لا يكتب إليه، ولا يُستدعى من أي مسار تشغيلي متكرر (AD-006 محقَّق حرفيًا).

**الخلاصة:** `app_subscriptions` **لا يزال يُقرأ ويُكتَب فعليًا** — لكن حصرًا من مسار Core Platform الأصلي (Dashboard/HomeController/التسجيل)، بمعزل تام عن كل منطق Marketplace الجديد. هذا **تعايش مقصود موثَّق**، لا تسرّب.

---

## 6. Direct URL / IDOR Audit — كل Endpoint حسّاس أُنشئ بـ1b/2A/2B

| Route | التحقق الفعلي بالـBackend | آلية التحقق |
|---|---|---|
| `POST /marketplace/{key}/activate` | لا معامل هوية خارجي — يعمل على `Auth::user()` مباشرة، مع `abort_unless` على شروط العنصر | ضمني بالتصميم (لا سطح هجوم) |
| `POST /marketplace/{key}/cancel` | `Auth::user()->marketplaceSubscriptions()->where(...)->firstOrFail()` | **Query Scoping** ضمني (لا Policy صريحة) — لا يقدر يلغي اشتراك غيره لأن الاستعلام نفسه مُقيَّد بـ`Auth::user()` |
| `GET /my/apps` | يعمل على `Auth::user()` فقط، لا معامل خارجي | ضمني |
| `POST /organization-context/{organization}` | `ActiveOrganizationContext::switchTo()` يتحقق من العضوية، Controller يحوّل الاستثناء لـ403 | **Domain Exception → HTTP 403** |
| `GET /organizations/{organization}/seats` | `$this->authorize('manageSeats', $organization)` | **Policy صريحة** |
| `POST /organizations/{organization}/subscriptions/{subscription}/seats/{user}` | `authorize()` + `ensureSubscriptionBelongsToOrganization()` + تحقق عضوية `$user` داخل `SeatService` | **Policy + تحقق يدوي مزدوج + تحقق داخل Service** (ثلاث طبقات) |
| `POST /organizations/{organization}/seats/{seat}/release` | `authorize()` + `ensureSubscriptionBelongsToOrganization($seat->subscription, ...)` | **Policy + تحقق يدوي** |

**ملاحظة توثيقية مهمة (لا تصنَّف كثغرة):** يوجد **نمطان مختلفان** للتحقق عبر هذي القائمة — Query Scoping ضمني (اشتراك شخصي) مقابل Policy صريحة (مؤسسي). كلاهما صحيح وظيفيًا (مُختبَر)، لكنه يعني عدم وجود اصطلاح واحد موحَّد لكل Endpoint حسّاس بالمشروع — راجع Section 9 للتفصيل الكامل.

**لم يُعثَر على أي Endpoint يعتمد على إخفاء زر بالواجهة فقط كخط حماية وحيد.**

---

## 7. Context Switching — التحقق الفعلي

| العملية | النتيجة |
|---|---|
| `Active=A → طلب` | يعمل، مُختبَر (`test_switching_between_two_organizations_shows_correct_isolated_access`) |
| `Active=B → طلب` | يعمل، لا اختلاط مع A |
| `Session مزوَّرة يدويًا` | `test_current_returns_null_for_organization_user_is_not_a_member_of_even_if_session_tampered` + `test_tampering_active_organization_context_session_does_not_bypass_membership_check` — كلاهما ناجحان |

**كل عملية حساسة تعمل فعليًا على Membership حقيقي مُعاد التحقق منه لحظة الفعل** — `ActiveOrganizationContext::current()` نفسه يستعلم `Auth::user()->organizations()->where('organizations.id', ...)` بكل استدعاء، لا يثق بقيمة الجلسة كمعرّف صالح تلقائيًا (AD-012 محقَّق على مستوى الكود، لا التوثيق فقط).

---

## 8. Lifecycle Matrix — الحالة الفعلية اليوم

| الحدث | Personal Access | Org Subscription | Seat | Org Access |
|---|---|---|---|---|
| User joins Org (`Membership` created) | لا تأثير | لا تأثير | لا مقعد تلقائي (فعل إداري صريح مطلوب) | لا تأثير |
| Seat assigned | لا تأثير | لا تأثير | `assigned` | `active` (يُنشأ إن لم يوجد) |
| Seat released | لا تأثير | لا تأثير | `released` | `revoked` فورًا (BR-2B-03) |
| User leaves Org (`Membership` deleted) | **لا تأثير** | **يبقى `active`** (لا يتأثر بمغادرة عضو واحد) | `released` (لهذا المستخدم بهذي المؤسسة فقط) | `revoked` |
| Subscription (مؤسسي) cancelled | لا تأثير | `cancelled` | **كل** المقاعد النشطة → `released` | **كل** الوصول النشط → `revoked` |
| Personal subscription cancelled | `cancelled` + Access `revoked` | لا تأثير | لا تأثير | لا تأثير |
| Organization changed (تبديل السياق) | لا تأثير (يبقى ظاهرًا دائمًا) | لا تأثير على البيانات، فقط ما يظهر بـMy Apps يتغيّر | لا تأثير | لا تأثير على البيانات — فقط الرؤية (My Apps) تتقيَّد بالسياق الجديد |

كل صف أعلاه مطابق لسلوك كود مُختبَر فعليًا (`SeatServiceTest`, `OrganizationSubscriptionServiceTest`, `MembershipRevokedSeatCleanupTest`) — لا صف افتراضي غير مُتحقَّق منه.

---

## 9. السؤال المحوري: هل لدينا Access Resolver واحد؟

**الجواب الصادق: لا، توجد ثلاث آليات مستقلة — وهذا صحيح معماريًا لأنها تجيب أسئلة مختلفة فعليًا، لكن التوثيق الصريح لهذا التعدد لم يكن موجودًا قبل هذي الوثيقة:**

1. **`EntitlementResolver`** — السؤال: "يقدر يستخدم هذا العنصر؟" (Entitlement). المستهلكون: `MarketplaceController`, `MyAppsController`.
2. **Query Scoping ضمني** (`Auth::user()->marketplaceSubscriptions()->where(...)`) — السؤال: "هل هذا السجل يخصّه أصلًا؟" (ملكية سجل، لا Entitlement ولا Authorization). المستهلك: `MarketplaceController::cancel()`.
3. **`OrganizationPolicy`** — السؤال: "يقدر يدير هذي المؤسسة؟" (Authorization إدارية). المستهلك: `OrganizationSeatController`, `SubscriptionsRelationManager`.

**هذا التعدد مقصود ومبرَّر معماريًا** (الأسئلة الثلاثة مختلفة فعليًا، دمجها بآلية واحدة كان سيخالف AD-005 بالضبط) — **لكنه غير موثَّق صراحة كنمط رسمي بأي وثيقة سابقة**، وهذا بحد ذاته فجوة توثيقية (لا تنفيذية) تستحق التسجيل.

### 9.1 — التكرار الحقيقي المُكتشَف (الفجوة الوحيدة غير المقصودة)

`MyAppsController::index()` يبني قائمة اشتراكات المؤسسة المرشَّحة بهذا الاستعلام المباشر:
```php
Subscription::where('subscriber_type', 'organization')
    ->where('subscriber_id', $activeOrganization->id)
    ->where('status', 'active')
    ->whereHas('accessAssignments', fn ($q) => $q->where('user_id', $user->id)->where('status', 'active'))
```
بينما `EntitlementResolver` (المُستدعى بعدها مباشرة لكل عنصر عبر `toAppEntry()`) يعيد نفس المنطق **بشكل مستقل تمامًا**:
```php
$orgSubscription = Subscription::where('subscriber_type', 'organization')
    ->where('subscriber_id', $activeOrganization->id)
    ->where('marketplace_item_id', $item->id)
    ->where('status', 'active')->first();
if ($orgSubscription) { $hasSeatAccess = $orgSubscription->accessAssignments()->where('user_id', $user->id)->active()->exists(); ... }
```
**النتيجة عمليًا صحيحة اليوم** (كلا الاستعلامين يعبّران عن نفس قاعدة العمل بصيغتين مختلفتين، مُختبَر ويعمل صح) — **لكن هذا بالضبط النمط اللي حذّرت منه:** قاعدة وصول واحدة مُعبَّر عنها بمكانين مستقلين. لو تغيّر تعريف "وصول مؤسسي فعّال" مستقبلًا (مثال: إضافة حالة `suspended` على `AccessAssignment` المذكورة بوثيقة تصميم 2B قسم E)، **يجب تحديث الاثنين معًا يدويًا** — لا ضمانة بنيوية تمنع انحرافهما عن بعض. هذا التكرار **لم يُصلَح هنا** بأمرك الصريح — موثَّق فقط.

**لا تكرار مماثل وُجِد بأي مكان آخر** (فُحصت كل نقاط الاستدعاء أعلاه بقسم 1).

---

## 10. الفجوة الأهم بالتدقيق كامل: نظاما وصول متوازيان، لا نظام واحد

هذي أهم نتيجة بالوثيقة كاملة، تستحق تسليط ضوء منفصل عن كل الأقسام أعلاه:

```
النظام القديم (Core Platform Phase 1):
  app_subscriptions → User::hasActiveSubscription() → Dashboard القديم فقط

النظام الجديد (Marketplace Phase 1b+):
  subscriptions/access_assignments → EntitlementResolver → Marketplace/My Apps/Organization
```

**الاثنان يعملان اليوم بالتوازي التام، بلا أي مزامنة تلقائية بينهما** (AD-006 يمنع صراحة أي مزامنة تلقائية — قرار مقصود، لا سهو). **الأثر العملي الملموس:** مستخدم فعّل بوابة معرفة عبر زيارة `/marefa` أو `/dashboard` قبل وجود `MarketplaceCatalogSeeder`/`SubscriptionService` (أو ببساطة عبر ذاك المسار القديم فقط، بلا مرور بـ`/marketplace`) — سيظهر له "مفعّل" باللوحة **القديمة**، بينما شاشات Marketplace الجديدة (My Apps، بادج "مفعّل لديك") **لن تعكس ذلك تلقائيًا** إلا بعد تشغيل `marketplace:backfill-free-access` يدويًا (أداة ترحيل بأمر واحد، غير مجدوَلة، غير تلقائية).

**هذا موثَّق ومقصود منذ تقرير Phase 1b** (نُفِّذ الترحيل مرة واحدة يدويًا وقتها) — **لكن لا آلية مستمرة تمنع تكرار نفس الفجوة لمستخدمين جدد يمرّون فقط بالمسار القديم لاحقًا.** هذي أقرب لفجوة تشغيلية/Ops من ثغرة أمنية — لا تسرّب بيانات بين مستخدمين مختلفين، فقط عدم اتساق محتمل بحالة "وصول" لنفس المستخدم بين شاشتين مختلفتين بالنظام.

---

## الخلاصة والتوصية

**لا ثغرة تسرّب بيانات بين مؤسستين وُجِدت.** كل مسارات Tenant Isolation المفحوصة (9 طبقات) سليمة، كل Endpoint حسّاس مفحوص له تحقق Backend حقيقي (لا اعتماد على الواجهة)، كل سيناريو Context Switching مُختبَر ويعمل صح.

**فجوتان حقيقيتان مُوثَّقتان، بدون إصلاح (بأمرك):**
1. **تكرار منطقي غير مقصود** بين `MyAppsController` و`EntitlementResolver` لنفس قاعدة "وصول مؤسسي فعّال" (Section 9.1) — خطر انحراف مستقبلي، لا خطر حالي.
2. **نظاما وصول متوازيان** (قديم/جديد) بلا مزامنة مستمرة تلقائية (Section 10) — خطر عدم اتساق تشغيلي، لا خطر أمني.

**فجوة توثيقية واحدة** (لا تنفيذية): تعدد آليات "فحص الوصول" الثلاث (Section 9) لم يكن مُسمًّى ومُبرَّرًا صراحة بأي وثيقة سابقة — الآن موثَّق هنا.

**التوصية (بدون إلزام):** المعمارية **لا تحتاج Hardening أمني طارئ قبل 2C** — لا ثغرة حقيقية بمعنى "بيانات مؤسسة تصل لمستخدم لا يستحقها". لكن يستحق النظر (قرارك): توحيد استعلام Section 9.1 داخل `EntitlementResolver` فقط (لا حاجة له بمكان ثانٍ)، كخطوة نظافة بسيطة قبل أو أثناء 2C — وليست بوابة أمنية حرجة.
