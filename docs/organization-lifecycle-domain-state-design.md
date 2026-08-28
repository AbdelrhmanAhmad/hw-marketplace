# Organization Lifecycle — Domain State Enforcement — تصميم (قبل أي كود)

**الحالة:** تصميم فقط. **صفر كود، صفر Migration، صفر تعديل قاعدة بيانات.**
**القرار المسجَّل (AD-018، بالفعل بـ`marketplace-architecture-blueprint.md`/`marketplace-implementation-specification.md`):** مؤسسة `archived` لا يجوز تكتسب Marketplace Access جديدًا/مُوسَّعًا — قيد Domain State مستقل عن Authorization، لا يتجاوزه وجود Staff/Owner.
**المبدأ المُثبَّت:**
```
Authorization  = من يستطيع تنفيذ الفعل؟           → Gate/Policy
Domain State   = هل حالة الكيان تسمح بالفعل أصلًا؟  → State Guard (منفصل، يُفحَص بمعزل)
```

---

## 1. Mutation Inventory — كل عملية يمكن أن تُنشئ/تُفعِّل/توسِّع Marketplace Access

فُحص الكود الفعلي كاملًا (`grep` شامل على `app/Services`, `app/Http/Controllers`, `app/Models` لكل كتابة على `status`/`SubscriptionSeat`/`AccessAssignment`) — لا اعتماد على الذاكرة.

| # | العملية | الملف:السطر | تزيد الوصول؟ | مرتبطة بمؤسسة؟ |
|---|---|---|---|---|
| 1 | إنشاء اشتراك مؤسسي جديد | `OrganizationSubscriptionService::create()` — `:67` | **نعم — الفجوة (Finding E1)** | ✅ |
| 2 | رفع حد المقاعد | `OrganizationSubscriptionService::changeSeatLimit()` — `:99` | **نعم، شرطيًا** (رفع الحد فقط، لا التخفيض) | ✅ |
| 3 | إلغاء اشتراك مؤسسي | `OrganizationSubscriptionService::cancel()` — `:118` | لا (يُنقِص فقط) | ✅ |
| 4 | تعيين مقعد + منح وصول | `SeatService::assign()` — `:73-88` | **نعم** | ✅ |
| 5 | تحرير مقعد + سحب وصول | `SeatService::release()`/`performRelease()` | لا (يُنقِص فقط) | ✅ |
| 6 | إعادة تعيين مقعد | `SeatService::reassign()` | يستدعي `release()`+`assign()` — يرث فجوة #4 تلقائيًا | ✅ |
| 7 | تحرير كل مقاعد عضو (نظامي) | `SeatService::releaseAllForUserInOrganization()` | لا (يُنقِص فقط) | ✅ |
| 8 | إنشاء/تفعيل اشتراك **شخصي** | `SubscriptionService::subscribeUserToFreeItem()` | نعم، **لكن `subscriber_type='user'` دائمًا** | ❌ **لا علاقة بمؤسسة إطلاقًا — تحققت صراحة، لا Organization أي مكان بالملف** |
| 9 | إلغاء اشتراك شخصي | `SubscriptionService::cancel()` | لا | ❌ (نفس أعلاه) |
| 10 | إضافة Membership | `MembershipService::add()` | **سؤال مفتوح — راجع قسم 3** | ✅ (لكن ليست بالضرورة "Marketplace Access") |
| 11 | تغيير Role | `MembershipService::changeRole()` | لا تمنح Marketplace Access مباشرة (تغيّر صلاحيات إدارية فقط) | ✅ |
| 12 | نقل الملكية | `MembershipService::transferOwnership()` | لا تمنح Marketplace Access مباشرة | ✅ |
| 13 | حذف عضو | `MembershipService::remove()` | لا (يُنقِص فقط — يُطلِق `MembershipRevoked` الذي يُحرِّر المقاعد) | ✅ |
| 14 | أرشفة/استعادة | `OrganizationLifecycleService::archive()`/`restore()` | `archive` يُنقِص، `restore` لا يمنح شيئًا بذاته (لا Reactivation تلقائي، مؤكَّد) | ✅ |

**لا عملية رابعة عشرة موجودة تمنح وصولًا مؤسسيًا** — تحققت بـ`grep` شامل، هذا الجدول شامل الكود بأكمله.

**تأكيد حاسم:** الاشتراك الشخصي (#8/#9) **معزول تمامًا** عن أي مفهوم Organization — `SubscriptionService.php` لا يحتوي كلمة "Organization" بأي مكان. لا خطر تسرّب بين المسارين.

---

## 2. Domain State Matrix

| العملية | Active | Archived | السبب |
|---|---|---|---|
| Create Subscription | ✅ | ❌ | Finding E1 — يمنح وصولًا جديدًا كاملًا |
| Change Seat Limit — **رفع** الحد | ✅ | ❌ | يزيد القدرة القصوى للوصول (حتى لو الاشتراك نفسه `cancelled` — تناسق دفاعي، لا يعتمد على Finding E2 وحده) |
| Change Seat Limit — **تخفيض** الحد | ✅ | ✅ | يُنقِص فقط — آمن دائمًا، لا سبب للمنع |
| Assign Seat | ✅ | ❌ | يمنح وصولًا فعليًا مباشرًا (Finding E1's المسار الثاني) |
| Reassign Seat | ✅ | ❌ | يستدعي Assign داخليًا — نفس القيد تلقائيًا |
| Release Seat | ✅ | ✅ | يُنقِص فقط |
| Cancel Subscription | ✅ | ✅ (Idempotent — `archive()` نفسها تستدعيها) | يُنقِص فقط |
| Revoke Access | ✅ | ✅ | يُنقِص فقط |
| **Add Membership (غير-Owner)** | ✅ | **🟡 سؤال مفتوح** | راجع قسم 3 — ليست Marketplace Access بذاتها |
| **Add Membership (Owner، مؤسسة بلا Owner)** | ✅ | **🟡 سؤال مفتوح** | نفس أعلاه — إصلاح إداري لمؤسسة يتيمة قد يحدث بعد الأرشفة أيضًا |
| Change Role | ✅ | **🟡 سؤال مفتوح** | إداري بحت، لا يمنح Marketplace Access |
| Transfer Ownership | ✅ | **🟡 سؤال مفتوح** | نفس أعلاه |
| Remove Membership | ✅ | ✅ | يُنقِص فقط، لا سبب للمنع أبدًا |
| Archive | ✅ (فعل التحويل نفسه) | ✅ (Idempotent، لا فعل) | — |
| Restore | ❌ (غير منطقي، المؤسسة أصلًا Active) | ✅ | يعيد الحالة فقط، **لا يمنح وصولًا** (مؤكَّد، `restore()` لا تفعل شيئًا غير `status=active`) |
| Personal Subscription (Create/Cancel) | ✅ | **N/A — لا علاقة بمؤسسة إطلاقًا** | خارج النطاق كليًا (قسم 1، #8/#9) |

---

## 3. السؤال المفتوح — Membership ≠ Marketplace Access تلقائيًا (لا افتراض، كما طلبتَ)

**الحجة لصالح السماح بإضافة Membership لمؤسسة مؤرشَفة:**
- إصلاح إداري لمؤسسة مؤرشَفة (تصحيح بيانات Owner/Admin، تمهيدًا لاستعادتها لاحقًا) سيناريو واقعي — منعه قد يعيق نفس العملية اللي AD-017/Attack #5 صُمِّمت لتمكينها (Staff يُصلِح مؤسسات بحالة غير طبيعية).
- إضافة Membership **بذاتها لا تمنح أي Marketplace Access** — العضو الجديد لا يحصل على Seat/AccessAssignment تلقائيًا (مؤكَّد بقسم 1: `add()` لا تستدعي `SeatService` إطلاقًا). الوصول الفعلي يتطلب خطوة **منفصلة ومحظورة أصلًا** (`assign()`، #4 بالجدول).

**الحجة لصالح المنع:**
- منطقيًا، "لماذا تحتاج عضوًا جديدًا بمؤسسة لا نشاط فعلي لها؟" — قد يكون مؤشر استغلال أو خطأ تشغيلي.
- الاتساق: لو كل شيء آخر بمؤسسة مؤرشَفة "مجمَّد"، السماح بتعديل تركيبتها البشرية بينما هي مجمَّدة قد يبدو غير متّسق للمستخدم.

**توصيتي (لا قرار نهائي مني):** **السماح** — بناءً على الحجة الأولى تحديدًا (لا Marketplace Access ينتج مباشرة، ويخدم حالة إصلاح إداري واقعية طلبتَها أنت بنفسك بـAD-017/Attack #5). لكن هذا **قرارك أنت** — الجدول أعلاه يعكس 🟡 لحد ما تحسمه صراحة.

---

## 4. تصميم State Guard المركزي

**لا تكرار منطق** (نفس درس AD-017) — نقطة تحقق واحدة، تُستدعى من كل عملية "نعم" بعمود Archived أعلاه.

**الاسم المقترَح:** `Organization::assertCanReceiveNewMarketplaceAccess(): void` — تابع على الـModel نفسه (لا Service منفصل)، لأنه فحص حالة بحتة (`$this->status`)، بلا أي استعلام إضافي أو اعتماد خارجي — أبسط شكل ممكن، يطابق شرط "لا نبني إلا عند وجود حاجة فعلية".

```php
// Organization.php
public function assertCanReceiveNewMarketplaceAccess(): void
{
    if ($this->isArchived()) {
        throw new InvalidArgumentException('لا يمكن منح وصول Marketplace جديد لمؤسسة مؤرشَفة.');
    }
}
```

**نقاط الاستدعاء المقترَحة (بترتيب الأولوية):**
1. `OrganizationSubscriptionService::create()` — أول سطر بعد `Gate::authorize()` (يطابق مبدأ Finding #3 السابق: Authorization أولًا، ثم الفحوصات الأخرى).
2. `OrganizationSubscriptionService::changeSeatLimit()` — **فقط لو `$newLimit` يمثّل زيادة فعلية** (`$newLimit > $subscription->plan->seat_limit`) — لا تمنع التخفيض أبدًا.
3. `SeatService::assign()` — بعد `Gate::authorize()`، قبل أي منطق آخر.

**لماذا Model method لا Service/Trait منفصل:** الفحص لا يحتاج أي Dependency (لا Gate، لا استعلام قاعدة بيانات إضافي — `$this->status` متوفر بالفعل بالـinstance المُمرَّر). وضعه كـService منفصل (مثل `authorizeGrantingOwnership()`) كان سيكون Over-engineering لفحص بهذي البساطة — **قرار مصمَّم، ليس إغفالًا**؛ إن كنت تفضّل توحيد كل الأنماط (State Guards + Authorization Guards) بنفس الطبقة المعمارية (كلها داخل Services لا Models)، هذا قرار تصميم بديل مطروح لك أيضًا، لا فرق أمني بينهما.

---

## 5. Attack Matrix

| # | السيناريو | القناة | النتيجة المتوقَّعة بعد التصميم |
|---|---|---|---|
| 1 | Owner → إنشاء اشتراك لمؤسسته المؤرشَفة | استدعاء مباشر | ❌ `InvalidArgumentException` |
| 2 | Admin → إنشاء اشتراك لمؤسسة مؤرشَفة | استدعاء مباشر | ❌ (مرفوض أصلًا بـAuthorization، `manageSubscription` لا تشمل Admin — طبقتا حماية) |
| 3 | Member → إنشاء اشتراك | استدعاء مباشر | ❌ (مرفوض أصلًا بـAuthorization) |
| 4 | Platform Staff → إنشاء اشتراك لمؤسسة مؤرشَفة (مخوَّل Authorization-wise) | استدعاء مباشر | ❌ **State Guard يرفض رغم التخويل الكامل** — هذا بالضبط جوهر AD-018 |
| 5 | Platform Staff → تعيين مقعد على اشتراك مؤسسة مؤرشَفة | استدعاء مباشر | ❌ State Guard |
| 6 | Platform Staff → رفع حد مقاعد لمؤسسة مؤرشَفة | استدعاء مباشر | ❌ State Guard |
| 7 | نفس #4 عبر HTTP كامل (Controller حقيقي، لو وُجد مستقبلًا) | HTTP | ❌ (الحماية بالـService، لا تعتمد على طبقة الاستدعاء) |
| 8 | نفس #4 عبر Livewire (`SubscriptionsRelationManager::CreateAction`) | Livewire/Filament | ❌ نفس الحماية، بلا أي كود إضافي بـFilament (يُستدعى الـService نفسه) |
| 9 | تلاعب بمعرّف مؤسسة (IDOR) — تمرير `organization_id` مختلف عمّا يظهر بالواجهة | مباشر/Filament | ❌ لا علاقة — الفحص يعمل على `$organization` الفعلي المُمرَّر للـService، بصرف النظر عن أي معرّف بالواجهة |
| 10 | تلاعب بـ`active_organization_id` بالجلسة (`ActiveOrganizationContext`) | Session | ❌ **لا علاقة إطلاقًا** — `ActiveOrganizationContext` لا تُستخدَم بأي من التوابع الثلاثة المعنيّة (`create`/`changeSeatLimit`/`assign`) — كلها تستقبل `Organization`/`Subscription` كمعامل صريح، لا من الجلسة (AD-012 نفس المبدأ) |
| 11 | Restore ثم فورًا Create Subscription (تحقق المؤسسة أصبحت Active فعلًا) | مباشر | ✅ يُسمَح — هذا المسار الصحيح والوحيد لاستعادة الوصول، بالضبط كما صُمِّم |

---

## 6. الإجابة الصريحة على سؤالك

> **"هل توجد أي طريقة حالية يستطيع بها Actor مشروع، مهما كانت صلاحياته، إنشاء أو زيادة Marketplace Access لمؤسسة Archived؟"**

**نعم، اليوم قبل هذا الإصلاح.** مسارين محدَّدين بدقة (كلاهما بلا أي فحص حالة، بصرف النظر عن الفاعل):

1. `OrganizationSubscriptionService::create()` — أي فاعل يملك `manageSubscription` (Owner حقيقي أو Platform Staff) يُنشئ اشتراكًا **جديدًا نشطًا** لعنصر لم يكن مُشترَكًا به من قبل.
2. `SeatService::assign()` — أي فاعل يملك `manageSeats` (Owner/Admin/Staff) يُعيِّن مقعدًا (حتى على اشتراك موجود من قبل الأرشفة، الحالة المُوثَّقة بـFinding E2 — الفرق: هنا الاشتراك **نشط فعلًا** لو أُنشئ حديثًا عبر المسار #1، فالوصول **حقيقي**، لا مجرد بيانات غير متسقة).

**بعد هذا التصميم (حال اعتماده وتنفيذه): لا.** كلا المسارين يُغلَقان بنفس State Guard المركزي (قسم 4)، مُختبَرَين صراحة عبر كل قناة استدعاء ممكنة (قسم 5).

---

## 7. الملفات المتأثرة (للعرض فقط، لا تعديل الآن)

| الملف | التغيير المقترَح |
|---|---|
| `app/Models/Organization.php` | تابع جديد `assertCanReceiveNewMarketplaceAccess()` |
| `app/Services/OrganizationSubscriptionService.php` | استدعاء بـ`create()` (دائمًا) و`changeSeatLimit()` (عند رفع الحد فقط) |
| `app/Services/SeatService.php` | استدعاء بـ`assign()` |
| `tests/Feature/Organization/` | اختبارات جديدة (11 سيناريو بقسم 5، وصفًا فقط الآن) |
| `docs/marketplace-architecture-blueprint.md` | ✅ **مُنفَّذ بالفعل** — AD-018 مُسجَّلة |
| `docs/marketplace-implementation-specification.md` | ✅ **مُنفَّذ بالفعل** — AD-018 مُرحَّلة |

**لا تغيير على:** `MembershipService.php` (بانتظار قرارك بقسم 3)، `OrganizationLifecycleService.php`، أي Filament Resource (الحماية بالـService كافية، لا حاجة UI-level).

---

## ملخص القرارات المطلوبة منك

1. **قسم 3 — Membership/Role Change/Transfer Ownership على مؤسسة مؤرشَفة:** سماح (توصيتي) أم منع؟
2. **قسم 4 — موقع State Guard:** تابع على `Organization` Model (توصيتي، الأبسط) أم Service/Trait منفصل (لو تفضّل توحيد الطبقة المعمارية مع Authorization Guards)؟
3. **موافقة عامة على النطاق:** التوابع الثلاثة (`create`/`changeSeatLimit`-عند-الزيادة/`assign`) هي المسارات الوحيدة اللي تحتاج القيد — مؤكَّد بـInventory شامل (قسم 1).

**بانتظار موافقتك الصريحة قبل كتابة أي كود.** لا Header/Dashboard/Marketplace UI/Phase OL — كما هو مؤكَّد بكل مرة سابقة.
