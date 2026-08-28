# Hukm w Rakam — Marketplace & Ecosystem Architecture Blueprint

**الحالة:** مسودة للمراجعة — بدون أي تنفيذ. لا كود، لا Migrations، لا تعديل على أي ملف موجود.
**النطاق:** تصميم معماري تجاري وتقني كامل، وليس خطة تنفيذ فورية.
**القاعدة الحاكمة لكل قرار بهذي الوثيقة:**

> الهدف مو "نبني Marketplace قابل للتوسع". الهدف هو نبني **Core Platform** تقدر التطبيقات والتكاملات والخدمات تعتمد عليها دون ما تضطر كل وحدة تعيد بناء المستخدمين، الصلاحيات، الاشتراكات، البيانات، الذكاء الاصطناعي، الملفات، والإشعارات من الصفر.

**قاعدة ثابتة ثانية، بنفس وزن الأولى:**

> **Future-ready architecture ≠ Future-built software.** نصمم البنية بحيث ما تمنعنا من الوصول لما نحتاجه مستقبلًا (خطط مدفوعة، تكاملات، AI، شركاء خارجيون) — لكن ما نبني أي وحدة فعلية منها **قبل** وجود حاجة حقيقية ملموسة لها. أي قسم بهذي الوثيقة يصف بنية بيانات أو نمط تصميم "جاهز لاحقًا" لا يعني إذن ببناء الكود أو الجداول الآن — الإذن الوحيد هو Phase 1 المحدّدة صراحة بقسم E.

تنويه حرج قبل أي شيء: حكم ورقم **الحقيقية** شغّالة فعليًا على `hw.sa` بمستخدمين حقيقيين. الكود المحلي اللي بنيناه بهذا المشروع (بوابة معرفة + طبقة Marketplace + محاكاة Core Platform محلية بـ Organizations/Memberships/AppSubscription) هو **نموذج أولي (Prototype) منفصل**، ما زال غير متصل بالنظام الحقيقي على `hw.sa`. أي قرار بهذي الوثيقة يفترض إنه سيُدمَج لاحقًا مع النظام الحقيقي، لا يستبدله. هذا التمييز يظهر بوضوح بقسم "Current Architecture" تحت.

---

## Architecture Decisions — Final (AD-001 – AD-005)

**الحالة:** معتمدة رسميًا (2026-08-08)، نتيجة مراجعة `marketplace-implementation-specification.md` وحسم ثلاثة تعارضات معمارية (AC-001/002/003) مسجَّلة هناك، بالإضافة لقرار رابع مستجد (AD-005). هذي القرارات **تُعدِّل** القرارات الأصلية أدناه (أقسام ٢، ٥، ٨) حيث يوجد تعارض صريح — لا تُلغيها، تُحدِّد ضوابطها التنفيذية بدقة أعلى.

### AD-001 — Audit Minimal Layer
**تعديل رسمي على قسم ٨ (Audit Logs "❌ غير موجود، JIT" بلا استثناء):** يوجد **استثناء ضيّق ومحدَّد** منذ Phase 1 — Audit Trail محدود (Append-only، غير مرئي للمستخدم، بلا واجهة) للأحداث **غير القابلة للاسترجاع لاحقًا** المتعلقة بالاشتراكات والوصول والمقاعد تحديدًا: Subscription Created/Activated/Cancelled/Suspended، Access Assigned/Revoked، Seat Assigned/Released. **لا** Audit Platform كاملة (لا UI، لا بحث متقدم، لا Analytics، لا Export، لا Dashboard) — هذي تبقى JIT كما بقسم ٨ الأصلي. السبب: بعض البيانات يمكن تأجيلها بأمان، لكن "من منح الوصول ومتى وبأي سياق مؤسسة" لا يمكن إعادة بناؤه بأثر رجعي لو لم يُسجَّل لحظة حدوثه.

**تعديل إضافي (Phase OL، 2026-08-13):** القائمة تُوسَّع بحدثين اثنين فقط — `OrganizationArchived`، `OrganizationRestored` — يسجّلان قرار Domain مهم (تجميد/إحياء مؤسسة كاملة، AD-015-adjacent)، بنفس مبرر AD-001 الأصلي حرفيًا (فعل غير قابل لإعادة البناء بأثر رجعي لو لم يُسجَّل لحظته). **لا توسّع عام للقائمة** — إضافة عضو/إزالة عضو/تغيير دور/نقل ملكية **لا تزال بلا حدث Audit اليوم رغم كونها أفعال Domain حساسة** (فجوة مسجَّلة صراحة بـAD-016 أدناه، لم تُغلَق بعد).

### AD-002 — Polymorphic Subscriber (تعديل ضوابط على قسم ٢)
يتم الإبقاء على `Subscription.subscriber_type/subscriber_id` Polymorphic (User/Organization)، **مشروطًا** بخمسة ضوابط إلزامية:
1. `subscriber_type` قيمة مغلقة بقيدين فقط (`user`, `organization`) — لا نص حر، لا نوع ثالث بدون قرار معماري جديد (لا Team/Company/Firm/Partner كـSubscriber مستقبلًا بذريعة مرونة الـPolymorphic).
2. إنشاء أي `Subscription` يمر **حصرًا** عبر طبقة Domain/Service — `Subscription::create()` المباشر من أي Controller/Livewire/Filament ممنوع معماريًا.
3. قيود قاعدة بيانات حيثما أمكن: صحة `subscriber_type`، Uniqueness المناسب، Indexes، اتساق Plan/Item.
4. كل عمليات تحديد "من صاحب الاشتراك" تمر عبر Repository/Resolver موحّد، لا استعلام مباشر متكرر.
5. اختبارات إلزامية: User subscription→User، Organization subscription→Organization، نوع غير صالح→مرفوض، Subscriber غير موجود→مرفوض، وصول عابر للمؤسسات→مرفوض.

### AD-003 — Concurrent Seat Allocation
إنفاذ `seat_limit` يتم عبر: Database Transaction + `lockForUpdate()` على صف الاشتراك + تحقق من جهة الخادم (لا يُعتمَد على الواجهة/JavaScript إطلاقًا كخط دفاع) + قيود قاعدة بيانات مساندة. الخادم هو مصدر الحقيقة الوحيد لمنع تجاوز الحد عند تزامن الطلبات. يتطلب اختبار Concurrency صريح، لا Unit Test عادي فقط.

### AD-004 — Organization Context Dependency
Phase 1 (كتالوج + وصول شخصي + Entitlement أساسي) **لا يعتمد** على Active Organization Context. **لا يبدأ** أي عمل Organization-level (اشتراكات مؤسسية، مقاعد، وصول مؤسسي، إدارة إدارية) قبل توفّر Active Organization Context فعليًا (Core Dependency منفصلة، خارج نطاق تنفيذ Marketplace المباشر).

### AD-005 — Entitlement ≠ Authorization (قرار جديد، يوسّع قسم ٥)
**Entitlement** يحدد: هل هذا المستخدم مؤهَّل أصلًا لاستخدام هذا العنصر؟ (مثال: "له وصول لإفلاس تك = نعم/لا"). **Authorization** يحدد: ماذا يقدر يفعل داخل التطبيق بعد حصوله على الوصول؟ (مثال: "يقدر يشوف قضية = نعم"، "يقدر يعدّلها = لا"، "يقدر يحذفها = لا"). **الاثنان لا يُدمجان أبدًا** — اشتراك المستخدم بتطبيق لا يعني تلقائيًا "يقدر يسوي أي شيء بداخله". السلسلة الكاملة الرسمية:
```
Authentication → Organization Context → Entitlement → Application Access → Authorization → Action
```
لا: `Subscription = Can Do Everything`. الـEntitlement طبقة Core Platform/Marketplace مشتركة (`EntitlementResolver`) — الـAuthorization طبقة **داخل كل تطبيق على حدة** (مثال: صلاحيات إفلاس تك الداخلية)، تُبنى فوق بيانات الأدوار/العضوية المشتركة من Core، لكن منطقها الخاص يبقى ملك التطبيق نفسه لا Marketplace.

### AD-006 — Legacy Source Migration Boundary (`app_subscriptions`)
`app_subscriptions`/`AppSubscription` (Core Platform Phase 1، ما قبل Marketplace) مصنَّف رسميًا **Legacy / Migration Source فقط** — لا مصدر تشغيل حي يُعتمَد عليه بأي كود جديد. **ممنوع** أي كود جديد يعتمد عليه أو يقرأ منه ابتداءً من هذي اللحظة. `marketplace:backfill-free-access` أداة ترحيل لمرة واحدة (One-time Migration Utility)، **ليست مسار تشغيل دائم** — لا تُستدعى من أي Controller/Job متكرر. لا حذف ولا كتابة متبادلة مع النظام الجديد (`subscriptions`/`access_assignments`) — القرار قائم من Phase 1b، يُثبَّت هنا كقاعدة معمارية دائمة.

### AD-008 — `SubscriptionSeat` كيان مستقل عن `AccessAssignment` (يحسم سؤال B.2 المفتوح بـ`phase-2-organization-access-design.md`)
`SubscriptionSeat` جدول مستقل، **لا** يُشتَق من عدّ `AccessAssignment`. الفرق مفاهيمي لا تقني فقط: **Seat = حق/سعة مخصَّصة داخل الاشتراك** (قرار إداري: "هذا المقعد مخصَّص لهذا المستخدم")، **Access = صلاحية استخدام فعلية** (قد تُعلَّق بمعزل عن سحب المقعد نفسه، مثال: تعليق تأديبي مؤقت لموظف بينما مقعده يبقى محجوزًا له إداريًا). هذا يسمح بحالة `Seat مخصَّص + Access معلَّق` كحالة صالحة ومصمَّمة، لا استثناء. السلسلة: `Subscription → SubscriptionSeat → User → AccessAssignment` — **ليست** `Subscription → عدّ AccessAssignment`.

### AD-009 — Audit Events: مسار المقعد منفصل دلاليًا عن مسار الوصول الشخصي
`SeatAssigned`/`SeatReleased` (مسار مؤسسي، فعل إداري: "خُصِّص/سُحِب مقعد") تبقيان **منفصلتين** عن `AccessGranted`/`AccessRevoked` (فعل استخدامي: "المستخدم صار/توقف يقدر يستخدم"). الفعلان قد يترافقان لحظيًا لكن ليس دائمًا (مثال: `SeatAssigned` بلا `AccessGranted` فوري لو الوصول يتطلب خطوة تفعيل إضافية لاحقًا). توضيح لـAD-001 لم يكن محسومًا وقت اعتماده (لغياب مسار مؤسسي وقتها) — القائمة المغلقة بـAD-001 تبقى كما هي بأربعة أحداث مؤسسية+شخصية، لا حدث خامس جديد يُضاف.

### AD-010 — Active Organization Context يُبنى أولًا (Phase 2A)، قبل أي طبقة أخرى
ترتيب Phase 2 الداخلي إلزامي: **2A** (Active Organization Context) → **2B** (Organization Subscription) → **2C** (Seat Management) → **2D** (Organization Access) → **2E** (Organization Authorization). لا 2B تبدأ قبل اعتماد 2A ونجاحه — كل طبقة لاحقة تفترض وجود سياق مؤسسة نشط موثوق، بناؤها قبله يعني إعادة تفسير لاحقة لكل عملية وصول.

### AD-011 — Active Organization Context ≠ Organization Authorization
اختيار المستخدم لمؤسسة نشطة (تبديل السياق) **لا يمنحه تلقائيًا أي صلاحية إدارية بتلك المؤسسة**. السياق يحدد "بأي هوية يعمل الآن" فقط — الصلاحيات الفعلية تُحسَب دائمًا من `Membership.role` الحقيقي بتلك المؤسسة تحديدًا، لا من مجرد وجود سياق نشط. السلسلة الكاملة المُحدَّثة:
```
Authenticated User → Active Organization Context → Membership → Organization Role → Entitlement → Application Access → Application Authorization
```
مثال الحسم: مستخدم `Partner` بمؤسسة A (يدير أعضاء) و`Lawyer` بمؤسسة B (لا يدير) — تبديل السياق لـB **يُسقِط تلقائيًا** أي صلاحية إدارية كان يملكها بسياق A، بلا تسرّب أو تأخّر بالتحديث.

### AD-012 — Active Organization Context = Pointer فقط، لا مصدر حقيقة للصلاحيات/العزل
`session('active_organization_id')` يجاوب سؤالًا واحدًا فقط: **"أي مؤسسة يريد المستخدم يعمل ضمنها الآن؟"** — **لا** "وش يقدر يوصل له؟". الجلسة قابلة للتغيير (تلاعب Cookie، حالة قديمة)، فلا يصح الوثوق بها كخط تحقق وحيد لأي قرار وصول/عزل حسّاس. **كل** قرار وصول/عزل حقيقي (Subscription مؤسسي، Seat، Access، بيانات تشغيلية Tenant-scoped) **يعيد التحقق من `Membership` الفعلية بقاعدة البيانات مباشرة عند نقطة الفعل نفسها** — لا يكتفي بقراءة السياق النشط والثقة به. السلسلة الصحيحة دائمًا:
```
Authenticated User → Membership (تحقق مباشر بقاعدة البيانات) → Organization → Authorization
```
لا:
```
session('active_organization_id') → Authorization
```
Middleware التحقق من صحة السياق (Phase 2A) **تبسيط تجربة المستخدم** (تصحيح جلسة قديمة بصمت) — **ليس** بديلاً عن التحقق المستقل بكل نقطة فعل حسّاسة لاحقة (Phase 2B فصاعدًا). هذا القيد يُطبَّق بلا استثناء على أي كود مستقبلي يستهلك السياق النشط.

### AD-013 — Single Source of Truth for Effective Marketplace Access
أي تعريف مستقبلي لـ"الوصول الفعّال لعنصر Marketplace" (Effective Access) **يجب** يكون له مصدر Domain واحد (`EntitlementResolver` حصرًا اليوم) — **لا يجوز** أي `Controller`/`View`/طبقة أخرى تعيد بناء نفس منطق "هل وصول شخصي أو مؤسسي فعّال؟" باستعلام مستقل موازٍ، حتى لو كانت النتيجة صحيحة وقت الكتابة. اكتُشف هذا كتكرار حقيقي غير مقصود بـ`marketplace-access-control-audit.md` قسم 9.1 (`MyAppsController` مقابل `EntitlementResolver`) — لا خطر حالي، لكن خطر انحراف مستقبلي لو تغيّر تعريف "وصول فعّال" بمكان دون الآخر.

**توضيح حاسم — هذا لا يعني دمج المفاهيم الثلاثة المنفصلة عمدًا (تبقى كما هي، AD-005/AD-011):**
```
Entitlement  = يقدر يستخدم التطبيق؟           → EntitlementResolver حصرًا
Ownership    = هل هذا السجل يخصّه؟             → Query Scoping على Auth::user() (نمط منفصل، مقبول)
Authorization = يقدر يفعل وش بالضبط/يدير وش؟    → Policies (OrganizationPolicy، ولاحقًا داخل كل تطبيق)
```
AD-013 يخص **تكرار تعريف نفس السؤال (Entitlement) بمكانين مستقلين** — لا يخص وجود الأسئلة الثلاثة المنفصلة نفسها (ذاك تصميم صحيح ومقصود).

### AD-014 — Explicit User Intent Must Be Preserved
أي عملية Migration أو Backfill أو Sync (حالية أو مستقبلية) **لا يجوز** أن تُنشئ أو تُعيد تفعيل Access/Subscription لمستخدم إذا وُجد دليل تاريخي إن ذاك المستخدم ألغى/رفض/سحب ذاك الوصول صراحة — **إلا** عبر فعل إعادة تفعيل صريح من المستخدم نفسه أو مسؤول مخوَّل. اكتُشف هذا كخطر حقيقي مؤكَّد بالتجربة الفعلية (لا افتراض) بأمر `marketplace:backfill-free-access` — راجع `legacy-subscription-closure-plan.md` قسم 4.

**القاعدة الجوهرية (Domain Rule، لا تخص الـBackfill وحده):**
```
Inactive ≠ Never Subscribed
Cancelled ≠ Never Activated
```
لا يجوز لأي عملية ترحيل/مزامنة تستنتج "لا يوجد وصول فعّال الآن ← أُنشئ/أُعيد تفعيله" — الفحص الصحيح دائمًا **وجود أي سجل بأي حالة إطلاقًا** (`exists()`)، لا **غياب حالة فعّالة حاليًا** (`active()->exists()`). الفرق بين الاثنين هو بالضبط ما سبَّب الخطر المكتشَف. هذا القيد يُطبَّق على أي كود ترحيل/مزامنة مستقبلي بلا استثناء، لا حل نقطي لسطر واحد بأمر واحد.

### AD-007 — Marketplace Access ≠ Core Content Visibility
وجود نظام اشتراكات/وصول لـ Marketplace لتطبيق معيّن **لا يعني تلقائيًا** أن كل محتوى ذاك التطبيق يجب أن يصبح خلف تسجيل دخول أو خلف Entitlement. سياسة ظهور المحتوى (Content Visibility) قرار **مستقل** يخص كل تطبيق بمفرده، منفصل تمامًا عن طبقة Entitlement الخاصة بـMarketplace. المثال الحي: بوابة معرفة تبقى محتوى عام (بلا تسجيل دخول إلزامي) رغم وجود `Subscription`/`AccessAssignment` حقيقيَين لها بـPhase 1b — الطبقتان تتعايشان بقصد: Marketplace تدير "هل هذا العنصر جزء من بيئة عملك المُتتبَّعة؟"، لا "هل يحق لك تصفّح محتواه أصلًا؟". هذا المبدأ يُطبَّق على أي تطبيق حالي أو مستقبلي — تفعيل قيد دخول إلزامي على محتوى تطبيق قرار منتجي منفصل، لا نتيجة جانبية لبناء Marketplace.

### AD-015 — Marketplace Integrates Into Hukm w Rakam, Not The Reverse
**النص المرجعي الرسمي (بصيغته الحرفية المعتمدة من المستخدم — أي ترجمة أو إعادة صياغة لاحقة تبقى تابعة لهذا النص، لا بديلة عنه):**

> Hukm w Rakam is an existing, live production platform. The Marketplace is an additive capability integrated into Hukm w Rakam. It is not a replacement, rebrand, redesign, or architectural takeover of the existing Core Platform. No existing Core Platform behavior, navigation, identity, or user flow may be changed unless that change is explicitly approved as part of a separate scope.

**قيد دائم، يُطبَّق على كل Prompt/Spec قادم بلا استثناء (بطلب صريح من المستخدم).** له شقّان (شرح تفصيلي للنص المرجعي أعلاه، لا إضافة عليه):

**١. الاتجاه (Directionality):** Marketplace وحدة تُضاف **فوق** حكم ورقم الحالية — لا تُعاد هندسة هوية حكم ورقم، Navigation الحالي، أو سلوك أي خدمة قائمة "لأن Marketplace قادمة". أي نقطة دمج مستقبلية (إدخال Marketplace بالـHeader، Dashboard كـUnified Home) تبقى **إضافية بحتة**: Marketplace تدخل حكم ورقم، لا حكم ورقم تُعاد بناؤها لتصبح Marketplace.
```
حكم ورقم الحالية
      │
      ├── الوظائف الحالية        ← لا تتغيّر بسبب Marketplace
      ├── بوابة معرفة            ← لا تتغيّر بسبب Marketplace
      └── Marketplace  ← إضافة جديدة (Applications/Integrations/Services)
```
ليس:
```
Marketplace
    ↓
يستبدل حكم ورقم
```

**٢. الفصل بين "تحديد الاتجاه" و"البدء بالتنفيذ" (Sequencing):** أي وثيقة تُثبِّت Target Architecture لنقطة دمج مستقبلية (مثال: `dashboard-marketplace-transition-decision.md` واختيارها لـ"Unified Home") **لا تُشكِّل بذاتها إذنًا** ببدء العمل على تلك النقطة الآن. تحديد الاتجاه المعماري وتنفيذه إذنان منفصلان دائمًا — نفس بوّابة Design→Implementation المُطبَّقة بكل مرحلة سابقة بهذا المشروع (1a/1b/2A/2B/L1). الوثيقة تحسم "كيف ستتعايش Marketplace مع حكم ورقم يوم ندمجها بصريًا" — لا تعني "ابدأ الدمج البصري الآن".

**الأثر العملي الملزم لأي عمل مستقبلي على نقطة دمج (Header/Dashboard/Navigation):** إعادة استخدام مصدر الحقيقة الصحيح دائمًا (`EntitlementResolver`/My Apps، AD-013) بدل بناء منطق وصول موازٍ خاص بنقطة الدمج، والحفاظ الحرفي على Navigation/هوية حكم ورقم الحالية بلا تعديل غير ضروري.

### AD-016 — Organization Membership Domain Changes Must Be Auditable

**قيد مُسجَّل (2026-08-13)، بعد Phase OI — لم يُغلَق بعد، يبقى فجوة موثَّقة صراحة حتى يُعالَج:**

> كل تغيير Domain حساس في Organization Membership — إضافة عضو، إزالة عضو، تغيير Role، Transfer Ownership — يجب أن يكون قابلًا للتدقيق (Audit) قبل اعتبار Organization Lifecycle مكتملًا.

**السبب:** المشروع بنى Audit Trail قويًا للاشتراكات والوصول والمقاعد (AD-001) — لكن Phase OI (`MembershipService`) نفَّذت Last Owner Rule وTransfer Ownership **بلا أي حدث Audit مرافق**، لأنه لم يكن ضمن نطاقها المصرَّح به صراحة. هذا يعني اليوم: تغيير من يملك مؤسسة، أو من يديرها، **لا يترك أثرًا تاريخيًا** — بعكس كل فعل Marketplace/Subscription/Access/Seat آخر بالمشروع بأكمله. لمنصة مثل حكم ورقم، عدم القدرة على الإجابة لاحقًا على "من جعل هذا الشخص Owner؟ من أزال هذا العضو؟ متى تغيّرت الصلاحية؟" فجوة حقيقية لا تُترَك مفتوحة إلى الأبد.

**الحالة:** **غير مُغلَقة بـPhase OI عمدًا** (خارج نطاقها المصرَّح به وقتها) — تُعالَج إما ضمن Phase OL أو مرحلة Audit مستقلة لاحقة، أيهما أنظف معماريًا وقت اتخاذ القرار. **لا يُفتَرض حلها ضمنيًا بأي مرحلة قادمة إلا بتصريح صريح يذكرها.**

### AD-017 — Ownership Transfer Requires a Real Owner; Platform Staff Has No Exception Once One Exists

**قيد مُسجَّل (2026-08-16)، بعد Security Review #2 لـPlatform Authorization Foundation — يغلق فجوة مكتشَفة فعليًا بالتنفيذ، لا نظريًا:**

> إذا كان لمؤسسة Owner حقيقي (`Membership.role=Owner`) بالفعل، فإن أي فعل يمنح/ينقل/يُنشئ صلاحية Owner جديدة على تلك المؤسسة — بأي تابع، من أي مصدر استدعاء — لا يجوز إلا لفاعل هو نفسه Owner حقيقي بتلك المؤسسة تحديدًا. **Platform Staff لا يملك أي استثناء لهذي القاعدة بمجرد وجود Owner حقيقي واحد على الأقل** — بصرف النظر عن أي حاجة إدارية أو دعم فني مُتصوَّرة. الاستثناء الوحيد الباقي: مؤسسة **بلا Owner حقيقي إطلاقًا** — عندها فقط Platform Staff يقدر يؤسس أول Owner (لا تعديل على هذا الجزء، ثابت منذ Option D/Attack #5).

**السبب:** اكتُشف فعليًا عبر Security Review #2 (`docs/platform-authorization-security-review-2.md`، Finding H1) أن Hardening Pass الأول (أغلق Finding #1 الأصلي — `CreateAction`/`changeRole()`) ترك `MembershipService::transferOwnership()` خارج نفس القاعدة الموحَّدة. هذا أتاح مسارًا بديلًا بخطوتين، كل واحدة منهما مصرَّح بها بمفردها: Platform Staff يضيف نفسه كعضو غير-Owner (مسموح، `manageMembers`) ← يستدعي `transferOwnership()` لنقل الملكية لنفسه (كان مسموحًا، `transferOwnership` Ability تشمل Staff) — يُنتِج **بالضبط** نفس النتيجة المحظورة أصلًا (Staff يصبح Owner دائم، يبقى كذلك حتى بعد سحب صلاحية Staff). تحقَّق هذا بالتنفيذ الفعلي (اختبار حقيقي نُفِّذ وحُذف بعد التوثيق)، لا بالقراءة النظرية للكود فقط.

**القرار الحاسم للمستخدم (لا استثناءات):** أي حاجة إدارية مستقبلية لتغيير ملكية مؤسسة **لها Owner فعلي بالفعل** (مثلًا: مالك حقيقي غير قادر يوصل الدعم) **لا تُعالَج بتوسيع صلاحية Platform Staff الحالية** — تتطلب Domain Operation منفصل تمامًا، بصلاحيات وتدقيق مستقلين، يُصمَّم ويُعتمَد بمعزل لو احتيج فعليًا.

**الأثر العملي الملزم (قيد معماري دائم، لا يخص هذا الإصلاح وحده):** أي تابع — حالي أو مستقبلي — يمكن أن ينتج عنه `Membership.role=Owner` جديد (إنشاءً، ترقيةً، أو نقلًا) **يجب** يمر عبر نقطة تحقق مركزية واحدة (`MembershipService::authorizeGrantingOwnership()` اليوم) — **لا تكرار لمنطق مشابه بمكان مختلف**. هذا تعميم دائم للدرس المستفاد مرتين متتاليتين بنفس هذي المرحلة (Finding #1 الأصلي بـ`CreateAction`، ثم ثغرته الشقيقة بـ`changeRole()`، ثم Finding H1 بـ`transferOwnership()`) — كل واحدة كانت مسارًا مختلفًا لنفس النتيجة المحظورة، مما يثبت أن الحل الصحيح قاعدة واحدة موحَّدة تُطبَّق على **كل** مسار، لا إصلاحات نقطية متتابعة.

### AD-018 — Archived Organization Cannot Receive New or Expanded Marketplace Access

**قيد مُسجَّل (2026-08-17)، بعد Adversarial End-to-End Review لـOrganization Lifecycle + Authorization — يغلق فجوة مكتشَفة فعليًا بالتنفيذ:**

> مؤسسة بحالة `archived` **لا يجوز** بأي حال تكتسب Marketplace Access **جديدًا أو مُوسَّعًا** — لا اشتراك جديد، لا مقعد جديد، لا توسعة حد مقاعد تزيد القدرة الفعلية. هذا قيد **Domain State**، منفصل تمامًا عن Authorization: **وجود Platform Staff أو Owner حقيقي لا يتجاوز هذا القيد** — السؤال "هل حالة المؤسسة تسمح بهذا الفعل أصلًا؟" يُفحَص **قبل وبمعزل عن** السؤال "هل هذا الفاعل مخوَّل؟".

**السبب:** اكتُشف فعليًا (تنفيذ حقيقي كامل، لا نظري) عبر `docs/organization-lifecycle-authorization-e2e-security-review.md` (Finding E1) أن `OrganizationSubscriptionService::create()` لا تتحقق من `Organization::isArchived()` إطلاقًا — مؤسسة مؤرشَفة يمكن منحها اشتراكًا مؤسسيًا **جديدًا نشطًا**، ثم مقعدًا، وينتج عن ذلك **وصول حقيقي فعلي مؤكَّد عبر `EntitlementResolver`** لعضو بمؤسسة مُصنَّفة "مؤرشَفة" بكل مكان آخر بالنظام. هذا يُبطِل الوعد المركزي لـArchive نفسه (نص التأكيد بالواجهة: *"يُبطِل كل وصول — فورًا"*) — الأرشفة تمنع الوصول **الموجود وقتها فقط**، لا أي وصول **جديد** يُنشأ لاحقًا.

**الفرق الجوهري المُثبَّت بهذا القرار (لا يخص Archive وحدها):**
```
Authorization  = من يستطيع تنفيذ الفعل؟         → Gate/Policy (isPlatformStaff/Membership.role)
Domain State   = هل حالة الكيان تسمح بالفعل أصلًا؟ → State Guard (منفصل تمامًا، يُفحَص أولًا أو بمعزل)
```
كل مرحلة سابقة عالجت السؤال الأول بدقة متزايدة (Phase OI/Platform Authorization Foundation/AD-017). هذا القرار يُثبِّت السؤال الثاني كفئة تحقق مستقلة، إلزامية لأي عملية Lifecycle مستقبلية — **ليس خاصًا بـArchive وحدها**.

**الحالة: 🟢 مُغلَقة نهائيًا (2026-08-17).** نُفِّذت (`OrganizationMarketplaceAccessGuard`، نقاط استدعاء `create()`/`changeSeatLimit()` عند الزيادة/`assign()`)، ثم أُصلِح Race Condition نظري بينها وبين `archive()` (`create()` أصبحت تقفل صف المؤسسة قبل فحص الحالة، بنفس نمط `archive()`، راجع `docs/ad-018-race-condition-fix-completion-report.md`)، ثم رُوجِعت مرتين بمراجعة مستقلة (`docs/ad-018-security-review.md`، `docs/ad-018-security-review-2.md` — كلتاهما 🟢/🟡 غير حاجبتين). **القرار النهائي المعتمَد:** `Membership` **ليست** Marketplace Access — عمليات Membership (إضافة/تغيير Role/نقل ملكية) مسموحة على مؤسسة مؤرشَفة (AD-007)، لا تستدعي الـGuard إطلاقًا (مؤكَّد بـ`grep`). ملاحظة Race إضافية نظرية بـ`changeSeatLimit()`/`assign()` مقابل `archive()` (Finding مستقل، لا يحمل رقمًا) **حُلِّلت وأُقِرَّت كنظرية ذاتية التصحيح** (`docs/ad-018-seat-changeseatlimit-race-analysis.md`) — `cancel()` تُعيد استعلام المقاعد النشطة طازجًا بعد تحديث حالة الاشتراك، فتُبطِل تلقائيًا أي مقعد نتج عن تسابق لحظي، **بلا حاجة لتعديل كود إضافي**، قرار معتمَد صراحة.

---

## 1. تعريف الـ Marketplace Domain

### Application
منتج له واجهة استخدام مستقلة يدخلها المستخدم فعليًا ويشتغل بداخلها (بوابة معرفة، إفلاس تك مستقبلًا). له مزايا خاصة، صفحات خاصة، ويستهلك خدمات Core Platform (الهوية، المؤسسات، الإشعارات، الملفات) بدل ما يعيد بناءها.

### Integration
اتصال بين حكم ورقم ونظام خارجي (بوابة دفع، توقيع إلكتروني، محاسبة سحابية). ما له واجهة استخدام مستقلة داخل حكم ورقم — بس شاشات "ربط/إعداد/إدارة". دورة حياته مختلفة جذريًا عن التطبيق: Connect → Configure → Active → Error/Disconnected، بدل Subscribe → Use.

### Service
خدمة تُقدَّم للمستخدم، تقنية كانت أو بشرية (مثال: "مراجعة عقد من محامٍ"، "استشارة مالية"). هذا فعليًا امتداد لمفهوم "طلب الاستشارات" الموجود أصلًا بحكم ورقم الحقيقية — الفرق إنها الآن عنصر قابل للعرض والاشتراك بالـ Marketplace، مو ميزة مدمجة بالكود فقط. ممكن تُقدَّم من حكم ورقم نفسها أو من Partner (مكتب/مهني مسجّل كمزوّد خدمة).

### Partner
**ليس مجرد Vendor بسيط — كيان استراتيجي** قد يملك عدة عناصر Marketplace من أنواع مختلفة بنفس الوقت، لا عنصرًا واحدًا فقط. مثال حقيقي متوقّع: شريك تقني واحد قد يقدّم تطبيقًا **و** تكاملًا **و** خدمة معًا:

```
Partner: "شركة التقنية المالية"  (partner_type = technology_partner)
 ├── Application:  لوحة تحليل مالي
 ├── Integration:  ربط محاسبة سحابية
 └── Service:      استشارات إعداد تقارير
```

العلاقة `Partner (1) ──< (N) MarketplaceItem` **باتجاه واحد إلزامي** — عنصر Marketplace واحد ينتمي لشريك واحد، لكن الشريك يملك عددًا غير محدود من العناصر بأي مزيج من الأنواع الثلاثة. حقل `partner_type` (application_owner / accounting_firm / integration_provider / service_provider / technology_partner) يوصف طبيعة الشريك نفسه — منفصل تمامًا عن `MarketplaceItem.type` (اللي يوصف طبيعة *المنتج*). القيمة الافتراضية لكل العناصر الحالية الثمانية: شريك واحد ("حكم ورقم"، `partner_type = first_party`).

### العلاقة بينهم
```
Partner (1) ──< (N) MarketplaceItem
MarketplaceItem.type ∈ {application, integration, service}
MarketplaceItem (1) ──< (N) SubscriptionPlan
```

### Common Identity مقابل Type-specific Configuration — لتفادي God Object
اختيار كيان موحّد (`MarketplaceItem`) **لا يعني** جدول واحد ضخم يحمل عشرات الحقول nullable الخاصة بكل نوع (مثال: `webhook_url` و`auth_type` تخص Integration فقط، بينما `entry_route` يخص Application فقط، وكلاهما غير منطقي بصف Service). القاعدة المعمارية الفاصلة:

- **`marketplace_items` (الجدول المشترك) يحمل فقط الهوية العامة**: `key`, `type`, `partner_id`, `category_id`, `name`, `tagline`, `description`, `icon`, `status`, `billing_model`, `pricing_model`, `compatibility`. لا حقل خاص بنوع واحد يدخل هذا الجدول أبدًا — هذا القيد ثابت، لا استثناء له.
- **كل نوع له جدول تفصيلي منفصل 1:1 مع `marketplace_items`، يُنشأ فقط لما يصير عنده حقول فعلية يحتاجها**: `application_details` (مثال: `entry_route`)، `integration_details` (مثال: `auth_type`, `webhook_url`)، `service_details` (مثال: `delivery_type`: automated/human). هذا نمط **Class Table Inheritance** القياسي — لا نُنشئ الثلاثة جداول التفصيلية الآن (لا يوجد Integration أو Service حقيقي بعد)؛ ننشئ فقط اللي نحتاجه فعليًا لحظة الحاجة (نفس مبدأ Future-ready ≠ Future-built). المهم الآن هو **تثبيت النمط بالوثيقة**، لا بناء الجداول.

هذا القرار يضمن إن `marketplace_items` يبقى نحيفًا وثابت الحجم لسنوات، بغض النظر عن كم نوع/كم حقل خاص يضاف لاحقًا.

---

## 2. نظام الملكية والاستخدام (Ownership & Usage)

هذا فعليًا أهم قرار بالوثيقة كاملة لأنه يمس كل شيء فوقه.

### القرار: Polymorphic Subscriber
`Subscription.subscriber_type` ∈ {User, Organization} + `subscriber_id`. نفس الجدول يخدم الحالتين، بدل جدولين منفصلين (`user_subscriptions` و`organization_subscriptions`) كانوا بيضاعفون كل منطق الفوترة والـ Entitlements مرتين بدون داعٍ حقيقي — الفرق الوحيد هو "مين المدين"، مو شكل البيانات.

### كل `MarketplaceItem` يُصرِّح عن `billing_model`
`user_only` | `organization_only` | `both` — قرار العنصر نفسه، مو قرار عام على المنصة. بوابة معرفة (أداة فردية مجانية) = `user_only`. إفلاس تك (أداة مكتب) على الأغلب = `both` (محامٍ مستقل يشترك فرديًا، أو مكتب يشترك للجميع).

### الإجابة على أسئلة الأعمال المحددة

| السؤال | الإجابة المعمارية |
|---|---|
| هل يُشترى للمؤسسة أم للمستخدم؟ | يحدده `billing_model` بكل عنصر، مو قاعدة عامة موحّدة |
| هل المؤسسة تشتري ثم تحدد المستخدمين؟ | نعم — عبر `SubscriptionSeat`، يديرها من له دور Owner/Admin بالعضوية |
| تطبيقات User-level؟ | مدعومة — `billing_model = user_only` أو `both` |
| تطبيقات Organization-level؟ | مدعومة — `billing_model = organization_only` أو `both` |
| مقاعد (Seats)؟ | نعم — `SubscriptionPlan.seat_limit` + جدول `subscription_seats` |
| Enterprise subscription؟ | يُمثَّل كـ `SubscriptionPlan` من فئة أعلى (حدود مقاعد/مزايا أكبر)، **مو** جدول أو نوع كيان منفصل — التمييز بالبيانات (limits/entitlements) لا بالبنية |
| ماذا يحدث لو غادر الموظف المكتب؟ | حذف/إلغاء `Membership` يُطلق حدث `MembershipRevoked`، والمستمع (Listener) يُبطل تلقائيًا أي `SubscriptionSeat` مرتبط بذاك المستخدم بتلك المؤسسة — منطق مركزي بمكان واحد، مو معالجة يدوية متفرقة بكل شاشة |
| عضو بمكتبين؟ | مدعوم أصلًا (Membership علاقة N:N بين User وOrganization). صلاحيات/وصول المستخدم لتطبيق معيّن = **اتحاد (union)** كل الـ Seats الممنوحة له عبر كل عضوياته + أي اشتراك شخصي مباشر له — يُحسب وقت الطلب (computed)، ما يُخزَّن كحقل ثابت لتفادي تعارض بيانات |

### البدائل اللي استُبعدت ولماذا
- **جدول اشتراك منفصل لكل نوع مشترك (User/Org):** رُفض — تكرار منطق الفوترة والـ Entitlements بلا داعٍ، ويصعّب أي تقرير موحّد "كل اشتراكات هذا التطبيق".
- **حقل `is_org_subscription` boolean بدل Polymorphic:** رُفض — يفتح الباب لعمود `organization_id` nullable + `user_id` nullable بنفس الصف، وهذا يسمح بحالات غير صالحة منطقيًا (كلاهما فارغ أو كلاهما معبّى) بدون قيد قاعدة بيانات واضح. الـ Polymorphic Type يفرض القيد ببنية العمود نفسها.

### Tenant Isolation — أبعد من مجرد Membership
معالجة "عضو بمكتبين" بجدول `Memberships` تحل سؤال *الاشتراكات والصلاحيات* فقط. سؤال منفصل تمامًا وأخطر: **عزل البيانات التشغيلية** داخل كل تطبيق. مثال دقيق:

```
User (محامي واحد)
 ├── Organization A → دور: Lawyer   → بيانات قضايا مكتب A
 └── Organization B → دور: Partner  → بيانات قضايا مكتب B
```

لو نفس المستخدم فتح "إفلاس تك" وهو عضو بمكتبين، **لازم لا يشوف أي قضية من مكتب B وهو شغّال باسم مكتب A، ولا العكس** — حتى لو نظريًا Entitlements تسمح له بالوصول للتطبيق بكلا المؤسستين. هذا قيد بيانات (Data Scoping)، لا قيد صلاحية استخدام (Feature Access) — الاثنان مختلفان ويُعالَجان بطبقتين منفصلتين:

1. **Active Organization Context**: جلسة المستخدم تحمل دائمًا "أعمل الآن باسم أي مؤسسة؟" (نمط مألوف: مبدّل المساحة/Workspace Switcher بمنصات مثل Slack وNotion) — فعل صريح، لا افتراض ضمني. أي طلب داخل تطبيق يحمل هذا السياق معه إلزاميًا.
2. **إلزام معماري على كل تطبيق**: أي جدول بيانات تشغيلية خاص بتطبيق (قضايا إفلاس تك، مثلًا) **يجب** يحوي عمود `organization_id` (أو `user_id` للتطبيقات الفردية البحتة بلا مفهوم مؤسسة أصلًا مثل بوابة معرفة) كعمود تصفية إلزامي بكل استعلام — لا استثناء، ولا "تطبيق يقرر بنفسه لاحقًا". هذا القيد يُكتب بعقد داخلي لكل تطبيق (Application Contract)، ويُراجَع وقت أي مراجعة كود لتطبيق جديد ينضم للمنصة.

هذا يمنع تسرّب بيانات بين مؤسستين لنفس المستخدم، وهو أيضًا **حجر الأساس** لضمان عزل الذكاء الاصطناعي بين المؤسسات (قسم ٧).

---

## 3. Marketplace Catalog

بدل `PlatformApps::all()` (مصفوفة PHP ثابتة بالكود الحالي)، نحتاج جدول حقيقي. لكن — **بالضبط زي ما طلبت** — نحدد الـ Domain Model أولًا، ثم الحقول اللي نحتاجها فعليًا الآن، بدون إضافة أي جدول "لأنه يبدو مفيد".

### الحقول المطلوبة **الآن** (Required Now)
`marketplace_items`: `id`, `key` (slug فريد)، `type` (application/integration/service)، `partner_id` (nullable، افتراضيًا يشير لسجل "حكم ورقم" الداخلي)، `category_id`، `name`، `tagline`، `description`، `icon`، `status` (دورة الحياة، انظر قسم ٩)، `billing_model`، `pricing_model` (free/paid/freemium)، `compatibility` (JSON — نفس مفهوم `audiences` الموجود حاليًا: لمين هذا العنصر)، `created_at`/`updated_at`.

### الحقول المؤجَّلة (Future-ready — لا تُبنى الآن)
`media` (screenshots/gallery)، `documentation_url`، `version` + `marketplace_item_versions` (Changelog)، `reviews`/`ratings` — كل هذي **بلا محتوى حقيقي حاليًا** (نفس القرار السابق المعتمد بمرحلة تصميم الواجهة). العمود الوحيد المفيد إضافته من الآن كحقل فاضي (nullable) بلا استخدام فعلي هو `version` (default `'1.0'`) لأنه رخيص ولا يفرض أي منطق إضافي، وسيوفر عمود تريجر جاهز يوم نحتاج Changelog حقيقي.

### كيانات مرتبطة (Required Now)
- `marketplace_categories` — تصنيف بسيط (قانونية/مالية/محاسبية/تقنية...)
- `partners` — حتى لو سجل واحد فقط الآن ("حكم ورقم"، first-party)، لازم يوجد الجدول من البداية لأن `marketplace_items.partner_id` يعتمد عليه، وتفادي Migration مكسورة لاحقًا لإضافته

### كيانات مؤجَّلة (Future-ready)
- `marketplace_item_tags` (وسوم بحث متقدمة)
- Partner review/approval workflow tables (انظر قسم ١٠)

---

## 4. Subscription Architecture

### الكيانات
```
Organization ──┐
               ├──< Subscription >── SubscriptionPlan ──< PlanEntitlement
User ──────────┘         │
                          └──< SubscriptionSeat >── User
```

- **`Subscription`**: السجل التجاري — مين مشترك (Polymorphic)، بأي `MarketplaceItem`، بأي `SubscriptionPlan`، حالته (active/cancelled/past_due)، تواريخه.
- **`SubscriptionPlan`**: يتبع `MarketplaceItem` — الاسم (Basic/Professional/Enterprise)، `seat_limit` (nullable = بلا حد، أو للتطبيقات الفردية = 1 دائمًا)، السعر، دورة الفوترة.
- **`SubscriptionSeat`**: فقط لما `Subscription.subscriber_type = Organization` و`SubscriptionPlan.seat_limit` غير فارغ — يربط مستخدمين محددين بذاك الاشتراك. المدير (Owner/Admin بالعضوية) يدير من له مقعد.
- **`PlanEntitlement`**: مفتاح ميزة + قيمة/حد (مثال: `max_cases_per_month = 5` بخطة Basic، `unlimited` بخطة Enterprise لنفس التطبيق).
- **`AccessAssignment`**: الحلقة الفاصلة بين "مشترك" و"مخوَّل الآن" — انظر التنويه الحاسم أدناه. `user_id`, `subscription_id`, `status` (active/suspended/revoked), `granted_at`, `revoked_at`.

### تنويه حاسم: `Subscription` ≠ `Access` — دائمًا، حتى للاشتراك الفردي
**"مشترك" حقيقة تجارية (فوترة/دفع)، لا تعني تلقائيًا "له صلاحية استخدام فعلية الآن".** حتى بأبسط سيناريو — مستخدم فردي مشترك مباشرة بتطبيق مجاني — يمر القرار عبر خطوة وسيطة صريحة، لا افتراض مباشر. السلسلة الكاملة دائمًا أربع حلقات، بدون اختصار حتى بالحالة البسيطة:

```
Subscription  →  Entitlements  →  Access Assignment  →  User
(حقيقة تجارية)  (وش يسمح فيه)    (مين بالضبط مخوَّل الآن)  (الفاعل الفعلي)
```

- لاشتراك المستخدم المباشر (`subscriber_type = User`): يُنشأ سجل `AccessAssignment` تلقائيًا للمستخدم نفسه لحظة تفعيل الاشتراك — لكنه **سجل منفصل قابل للإلغاء بمعزل عن الاشتراك التجاري نفسه** (مثال: تعليق الوصول لمخالفة سياسة استخدام، بينما الاشتراك التجاري يبقى active وقت المراجعة).
- لاشتراك المؤسسة (`subscriber_type = Organization`): `AccessAssignment` يُنشأ فقط عبر `SubscriptionSeat` صريح — لا وصول تلقائي لأي عضو بالمؤسسة لمجرد وجود اشتراك مؤسسي فعّال.
- أي فحص وصول بالنظام (قسم ٥) يستعلم `AccessAssignment` — لا `Subscription` مباشرة أبدًا. هذا يفصل "هل يدفعون؟" عن "هل مسموح له الاستخدام الآن؟" كسؤالين مستقلّين تمامًا، وهو ما يسمح لاحقًا بحالات مثل تعليق مستخدم واحد دون التأثير على بقية المؤسسة، أو تعليق الاشتراك التجاري كاملًا دون حذف سجلات الوصول (Grace period).

### لماذا هذا التصميم مرن فعليًا
كل تطبيق يقرر بنفسه (عبر `billing_model` وعدد خططه): تطبيق مجاني بسيط = خطة وحيدة "Free" بمقعد واحد ضمني. تطبيق مكتبي = عدة خطط بمقاعد متفاوتة. **لا افتراض إن كل التطبيقات تتبع نفس نموذج الاشتراك** — هذا محقق فعليًا لأن `SubscriptionPlan` مرتبط بـ`MarketplaceItem` مباشرة، كل عنصر له خططه الخاصة بمعزل عن البقية.

### البدائل المستبعدة
- **حقل `seats` رقم ثابت داخل `Subscription` نفسها (بدل جدول `SubscriptionSeat` منفصل):** رُفض — ما يسمح بمعرفة *مين بالضبط* له مقعد، وهذا مطلوب لسيناريو "المدير يحدد المستخدمين المسموح لهم" المذكور صراحة بالمتطلبات.

---

## 5. Entitlements & Permissions

"المستخدم مشترك" غير كافية — نحتاج "المستخدم يقدر يسوي وش بالضبط". هذا امتداد مباشر لتنويه "Subscription ≠ Access" بقسم ٤.

```
Subscription → SubscriptionPlan → PlanEntitlement → AccessAssignment → Feature Check (Laravel Gate/Policy)
```

- عند أي فحص صلاحية داخل تطبيق (مثال: "يقدر يولّد مسودة قضية؟")، الاستدعاء يمر بـ `Gate::allows('use-feature', [$marketplaceItem, 'ai_case_draft'])`.
- الـ Gate يستعلم `AccessAssignment` المستخدم أولًا (**لا** `Subscription` مباشرة) — هل عنده سجل وصول فعّال لهذا العنصر، مباشر أو عبر مقعد بمؤسسة؟ إذا نعم، يتتبّع لـ`Subscription` المرتبطة ليقرأ `PlanEntitlement` المطابق ويرجّع مسموح/ممنوع + أي حد رقمي (Limit) مرتبط.
- هذا يسمح بمستويات (Basic/Professional/Enterprise) **داخل نفس التطبيق** بدون أي تغيير ببنية الجداول — فرق الخطط = فرق قيم `PlanEntitlement` فقط.

هذا يفتح الطريق مباشرة لقسم AI (٧) — استخدام الذكاء الاصطناعي نفسه يصير Entitlement قابل للقياس (مثال: `ai_calls_per_month`)، مو استثناء بمنطق منفصل.

### تنويه حاسم (AD-005): حدود هذا القسم — Entitlement لا يمتد لـObject-level Authorization
كل فحص بهذا القسم (`use-feature`, `PlanEntitlement`) يجاوب فقط "هل خطتك تشمل هذي الميزة؟" — سؤال مستوى **الخطة/التطبيق ككل**. هذا **لا يمتد أبدًا** لأسئلة مستوى **الكائن الفردي داخل التطبيق** (مثال: "يقدر هذا المستخدم يعدّل *هذي القضية تحديدًا*؟"، "يقدر يحذف *هذا الملف*؟"). هذي أسئلة **Authorization**، مسؤولية كل تطبيق بمفرده (إفلاس تك مثلًا يبني نظام صلاحياته الداخلي فوق بيانات الأدوار/العضوية من Core)، **لا** تُحل عبر `EntitlementResolver`/Marketplace بأي حال. الخلط بين الاثنين ممنوع معماريًا — راجع AD-005 أعلاه للسلسلة الكاملة.

---

## 6. Integrations Architecture

**قاعدة صارمة كما طُلب:** لا تكامل مباشر بين تطبيقين. كل شيء عبر المركز.

```
                 Hukm w Rakam Core
                        │
        ┌───────────────┼───────────────┐
        ↓                ↓                ↓
      Apps          Shared APIs      Integrations
                                          │
                              ┌───────────┼───────────┐
                              ↓           ↓           ↓
                         Accounting    Payment      Other
```

### الكيانات
- **`integration_connections`**: `subscriber` (Polymorphic User/Organization)، `marketplace_item_id` (التكامل نفسه)، `status` (connected/disconnected/error)، `credentials` (**مُشفَّرة** عبر Laravel `encrypted` cast — لا تُخزَّن نص صريح إطلاقًا)، `connected_at`، `last_synced_at`، `health_status`.
- **`integration_events`**: سجل تدقيق (Audit) لكل محاولة مزامنة/webhook — النجاح والفشل، مطلوب لأي قطاع منظّم (قانوني/مالي) من اليوم الأول مفاهيميًا، حتى لو التنفيذ لاحقًا.

### أنماط الاتصال
- **OAuth2**: الافتراضي لمزودين خارجيين حقيقيين (محاسبة سحابية، دفع) — كل `MarketplaceItem` من نوع `integration` يصرّح `auth_type`.
- **API Key**: لمزودين أبسط.
- **Webhooks واردة**: نقطة استقبال واحدة موحّدة `POST /integrations/{key}/webhook`، تُحوَّل فورًا لمهمة Queue (`ProcessIntegrationWebhookJob`) — **معالجة غير متزامنة إلزاميًا**، لا معالجة مباشرة أثناء الطلب، لتفادي إبطاء/كسر أي طرف بسبب بطء طرف ثاني.
- **Data Mapping**: كل تكامل يطبّق واجهة PHP مشتركة `IntegrationMapperInterface` (تحويل بيانات المزوّد الخارجي لصيغة حكم ورقم الداخلية) — الـ Core ما يعرف تفاصيل أي مزوّد، يتعامل بس مع الواجهة الموحّدة.
- **Disconnect/Revoke**: فعل صريح إلزامي يستدعي نقطة إلغاء الصلاحية عند المزوّد نفسه (مو حذف محلي فقط) — يُسجَّل بـ`integration_events` دائمًا، لأسباب تدقيق قانوني.

---

## 7. Shared AI Layer

**لا نبني "تطبيق ذكاء اصطناعي"** — نبني طبقة تُستهلَك من أي تطبيق يحتاجها.

```
AI Service Layer
├── LLM Gateway              (تجريد فوق مزوّد النموذج، مو ربط مباشر بكود كل تطبيق)
├── Model Selection          (أي نموذج استُخدم لأي طلب — قابل للتغيير دون كسر التطبيقات المستهلكة)
├── Prompt Management        (قوالب مُنسَّخة/بإصدارات، مو نص مكتوب داخل كل تطبيق)
├── Context Management       (تجميع السياق — permission-aware و tenant-aware، انظر أدناه)
├── RAG / Embeddings         (بحث دلالي عبر مخزن متجهات واحد، بعزل حسب المؤسسة/المالك)
├── Document Processing
├── Usage & Cost Tracking    (كل استدعاء: مين، أي تطبيق، أي نموذج، كم Token/دقيقة معالجة، أي تكلفة فعلية)
├── AI Permissions           (Policy منفصلة عن الصلاحيات العامة، لخطورة أعلى)
└── Audit Logs               (كل توليد يُسجَّل بشكل غير قابل للتعديل، مع مصدر البيانات المُستخدَم)
```

### النقطة الأهم — Permission & Tenant-Aware Context
الذكاء الاصطناعي **ما يشوف بيانات حكم ورقم كلها تلقائيًا**. أي `ContextProvider` يجمع سياق لاستدعاء AI يُقيَّد بقيدين معًا، لا بقيد واحد:
1. **صلاحيات المستخدم** (Permission-aware) — لو المستخدم ما يقدر يشوف قضية مكتب ثانٍ كإنسان، الذكاء الاصطناعي المُستدعى نيابة عنه ما يقدر يشوفها كذلك.
2. **Active Organization Context** (Tenant-aware، مرتبط مباشرة بقسم Tenant Isolation تحت الملكية والاستخدام) — لو المستخدم عضو بمكتبين، أي سياق يُجمَّع لطلب AI يُقيَّد **بالمؤسسة النشطة وقت الطلب فقط**، ولو كان المستخدم نفسه له وصول تقني للمؤسستين. تسرّب بيانات مكتب A لسياق طلب يخص مكتب B **ممنوع معماريًا**، لا سياسة إدارية فقط.

هذا مو تفصيل تنفيذي، هذا **قيد معماري إلزامي** لأي تطبيق قانوني/مالي حساس.

### التدقيق والتكلفة (Auditability) — إلزامي من التصميم، لا إضافة لاحقة
لأن المنصة قانونية ومالية، كل استدعاء AI يُسجَّل **إلزاميًا** بالأبعاد التالية (لا اختياري، ولا "نضيفه لو احتجناه"):
- **مين** استدعى (User + Active Organization Context)
- **أي تطبيق** استدعى
- **أي نموذج** استُخدم بالضبط (Model Selection قابل للتتبّع، ضروري لو تغيّر النموذج لاحقًا وأثّر على نتيجة تاريخية)
- **أي إصدار Prompt** استُخدم (Prompt Versioning — نفس القالب يتغيّر بمرور الوقت، ولازم نعرف أي نسخة أنتجت أي نتيجة)
- **مصدر البيانات** اللي دخلت بالسياق (Data Provenance — أي مستندات/سجلات فعليًا غُذِّيت للنموذج، ليس فقط "كان عنده صلاحية يشوفها")
- **الاستهلاك والتكلفة** (Tokens/وقت معالجة/تكلفة فعلية) لكل استدعاء، مربوطة بالمستخدم والتطبيق والمؤسسة
- **النتيجة نفسها**، بشكل غير قابل للتعديل بعد التسجيل (Immutable Audit Log)

هذا يخدم غرضين معًا: **المسؤولية القانونية** (Legal Defensibility — يقدر حكم ورقم يثبت وش بالضبط أنتج أي قرار/مسودة بأي وقت)، **والتحكّم التجاري** (قياس تكلفة AI الفعلية لكل تطبيق/مؤسسة، أساس لأي تسعير مبني على الاستهلاك مستقبلًا).

### كيف تستهلكه التطبيقات
كل تطبيق يتعامل مع `AIServiceInterface` (PHP contract)، مو استدعاء مباشر لمزوّد نموذج بكوده الخاص. استخدام الذكاء الاصطناعي نفسه Entitlement قابل للقياس (قسم ٥) — يفتح الباب لخطط تسعير مبنية على الاستهلاك مستقبلًا بدون أي تغيير بنيوي.

---

## 8. Shared Platform Services

هذي فعليًا اللي تحدد إذا حكم ورقم "منصة" فعلية أو لا — قائمة الخدمات المشتركة اللي ما يصح أي تطبيق يعيد بناءها:

| الخدمة | الحالة الحالية | الفجوة |
|---|---|---|
| Identity | ✅ مبني (Breeze) | — |
| Organizations | ✅ مبني (أساسي) | لا يدعم بعد Seats/Entitlements |
| Permissions | ⚠️ جزئي | `MembershipRole` موجود، بدون طبقة Entitlement/Gate مربوطة بالـ Marketplace |
| Billing | ❌ غير موجود | لا بوابة دفع، لا نموذج فاتورة |
| Notifications | ❌ غير موجود | حتى الجدول غير موجود — لوحة التحكم تذكر "تنبيهات" مفاهيميًا بدون أي نموذج خلفه |
| Files | ❌ غير موجود | لا خدمة ملفات موحّدة بعزل صلاحيات على مستوى المؤسسة |
| Search | ⚠️ جزئي | بحث الأنظمة ببوابة معرفة موجود، وبحث بسيط بالمتجر — لا بحث موحّد عابر للمنصة |
| Audit Logs | ⚠️ استثناء ضيّق (AD-001): Audit Minimal لأحداث الاشتراك/الوصول/المقاعد من Phase 1 — لا منصّة تدقيق كاملة | واجهة/بحث/Analytics/Export/Dashboard تبقى JIT |
| AI | ❌ غير موجود | انظر قسم ٧ |
| Payments | ❌ غير موجود | — |
| Events | ⚠️ جزئي | نظام Laravel Events متاح إطاريًا، لا أحداث Domain فعلية مُعرَّفة (مثال: لا يوجد `MembershipRevoked` بعد) |
| API Gateway | ❌ غير موجود | كل الواجهة حاليًا Blade من جهة الخادم — لا REST/GraphQL API لأي طرف خارجي (شريك) يتكامل معه |

**قاعدة البناء:** كل خدمة من هذي تُبنى Just-In-Time — أول ما أول ميزة حقيقية تحتاجها فعليًا، مو مسبقًا "لأنها قد تلزم". هذا امتداد مباشر لقرارك السابق بعدم إضافة محتوى/بنية وهمية.

---

## 9. Marketplace Lifecycle

```
Draft → Submitted → Under Review → Approved → Published → Updated → Suspended → Deprecated
```

- عناصر **First-party** (حكم ورقم نفسها): تتخطى Submitted/Under Review — مسار مباشر `Draft → Published` بقرار إداري (Filament).
- عناصر **Partner**: تمر بكامل المسار — `Submitted` يُطلقها الشريك، `Under Review` مراجعة داخلية، `Approved` قرار، `Published` ظهور فعلي بالمتجر.
- `Updated`: نسخة جديدة من عنصر منشور بالفعل — تتطلب مراجعة مصغّرة لا كاملة (حسب حجم التغيير، قرار سياسة مستقبلي).
- `Suspended`: إخفاء مؤقت (مشكلة أمنية/شكوى) بدون حذف بيانات أو اشتراكات — الاشتراكات القائمة تبقى، الظهور بالكتالوج فقط يتوقف.
- `Deprecated`: نهاية دعم مخطط لها — إشعار مسبق للمشتركين قبل الإيقاف الفعلي.

هذا كله **تصميم مفاهيمي الآن**، بدون أي واجهة إدارة مراجعة (Partner Review UI) — يُبنى فقط لمن أول شريك خارجي فعلي يقترب.

---

## 10. Partner Ecosystem

مخطط مفاهيمي بدون بناء (كما طلبت — نحدد الآن، نبني لاحقًا):

1. **تسجيل الشريك**: نموذج طلب (شركة/فرد) + معلومات تعريفية وقانونية أساسية
2. **تقديم المنتج**: إنشاء `MarketplaceItem` بحالة `Draft` مرتبط بـ`partner_id`
3. **المتطلبات**: معايير جودة/أمان دنيا (تُحدَّد لاحقًا بسياسة منفصلة، ليست جزءًا من هذي الوثيقة التقنية)
4. **المراجعة**: مسار `Submitted → Under Review → Approved/Rejected` (قسم ٩)
5. **API Access**: عند القبول، إصدار API Key/OAuth Client محدود النطاق (Scope) لذاك الشريك فقط
6. **النشر**: `Published` — ظهور بالمتجر
7. **إدارة النسخ**: `Updated` lifecycle transitions
8. **الدعم**: قناة تواصل بين مستخدمي حكم ورقم ودعم الشريك (تصميم لاحق)
9. **تقاسم الإيرادات**: حقل `revenue_share_percentage` على `partners` كنقطة بداية — منطق الحساب والتحويل الفعلي خارج نطاق هذي الوثيقة التقنية (مالي/قانوني بالدرجة الأولى)

---

# A. Current Architecture — ما هو موجود فعليًا الآن

### حكم ورقم الحقيقية (hw.sa)
منصة حية بمستخدمين حقيقيين: دليل مكاتب/محامين، صفحة مكتب، صفحة محامي، طلب استشارات. **هذا النظام خارج نطاق أي عمل تم بهذا المشروع المحلي** — لم يُطَّلع عليه كودًا، ولم يُمَس بأي شكل.

### النموذج الأولي المحلي (هذا المشروع)
- **الهوية**: Laravel Breeze، جدول `users` واحد، بريد إلكتروني وكلمة مرور، بدون OTP
- **Core Platform محلي (محاكاة)**: `organizations`، `memberships` (بدور من `MembershipRole` enum)، `app_subscriptions` — **User-level حصرًا، لا يدعم Organization-level أو Seats إطلاقًا**
- **Marketplace**: `App\Support\PlatformApps::all()` — مصفوفة PHP ثابتة، ٨ عناصر، بدون جدول قاعدة بيانات، بدون تمييز نوع (الكل يُعامَل كـ"تطبيق" ضمنيًا)، بدون شريك، بدون خطط، بدون دورة حياة
- **التطبيقات الفعلية**: بوابة معرفة فقط (فهرس أنظمة، بحث، حاسبة، مفضلة) — الباقي بطاقات تسويقية "قريبًا" + نموذج تسجيل اهتمام (`service_interests`) يعمل فعليًا كأداة قياس طلب
- **AI**: غير موجود إطلاقًا، ولا حتى كنقطة امتداد
- **Integrations**: غير موجود كمفهوم بالكود
- **Billing**: غير موجود
- **الإدارة**: لوحات Filament لمحتوى بوابة معرفة، الـ leads، والمؤسسات/الاشتراكات (CRUD أساسي)

---

# B. Target Architecture

ملخّص — التفاصيل الكاملة بالأقسام ١-١٠ أعلاه. المكونات الجديدة المطلوبة:

`marketplace_items` (هوية مشتركة نحيفة فقط، عبر `MarketplaceCatalogRepository` بديلة عن `PlatformApps` الثابتة، بفترة تعايش انتقالية) · جداول تفصيلية لكل نوع (`application_details`/`integration_details`/`service_details`، تُنشأ عند الحاجة الفعلية فقط) · `partners` (بحقل `partner_type`، يملك عدة عناصر بأنواع مختلفة) · `marketplace_categories` · `subscription_plans` · `plan_entitlements` · `subscription_seats` · إعادة تسمية/توسيع `app_subscriptions` → `subscriptions` (Polymorphic subscriber) · **`access_assignments`** (الفاصل الإلزامي بين "مشترك" و"مخوَّل الآن") · **Active Organization Context** بالجلسة (Tenant Isolation) · `integration_connections` + `integration_events` (عند الحاجة الفعلية) · `AIServiceInterface` بأبعاد تدقيق كاملة (نموذج، إصدار Prompt، مصدر بيانات، تكلفة) (عند الحاجة الفعلية) · طبقة `Gate`/Policy موحّدة للـ Entitlements تستعلم `AccessAssignment` لا `Subscription` مباشرة.

---

# C. Gap Analysis

| المكوّن المستهدف | الحالة الحالية | الفجوة |
|---|---|---|
| Marketplace Catalog حقيقي بقاعدة بيانات (هوية نحيفة + تفاصيل بجداول منفصلة حسب النوع) | مصفوفة PHP ثابتة | كامل — لا جدول، لا CRUD إداري، لا فصل Common/Type-specific |
| تمييز Application/Integration/Service | كل شيء "تطبيق" ضمنيًا | كامل — لا حقل `type` |
| Partner كـ كيان (يملك عدة عناصر بأنواع مختلفة) | غير موجود | كامل |
| اشتراك على مستوى المؤسسة | غير مدعوم | كامل — `app_subscriptions.user_id` فقط |
| Seats | غير موجود | كامل |
| Subscription Plans / Entitlements | غير موجود (اشتراك ثنائي فعّال/ملغى فقط) | كامل |
| فصل Subscription عن Access Assignment | غير موجود — "مشترك" = "مخوَّل" ضمنيًا | كامل — خطر منطقي حقيقي لو دخلنا الاشتراكات المؤسسية بدونه |
| Tenant Isolation (Active Organization Context + عزل بيانات التطبيقات) | غير موجود | كامل — خطر تسرّب بيانات بين مؤسستين لنفس المستخدم |
| صلاحيات على مستوى الميزة داخل التطبيق | غير موجود | كامل |
| Integrations Architecture | غير موجود كمفهوم | كامل |
| AI Shared Layer (بأبعاد تدقيق/تكلفة/عزل مؤسسات) | غير موجود | كامل |
| Notifications/Files/Search الموحّدة/Audit/Payments/API Gateway | غير موجودة أو جزئية | كبيرة — تُبنى JIT حسب قسم ٨ |
| Marketplace Lifecycle | كل العناصر "منشورة" ضمنيًا بلا حالة | كامل |
| Partner Ecosystem | غير موجود | كامل (متوقّع — مستقبلي بالوثيقة التأسيسية نفسها) |

---

# D. Migration Plan

**المبدأ:** تحويل تدريجي إضافي (Additive)، صفر تغيير سلوك مرئي بالمرحلة الأولى، صفر مساس بـCore Platform الحالية أو ببوابة معرفة الوظيفية.

### Compatibility Layer — لا استبدال مباشر لـ PlatformApps
**لا يُحذف `PlatformApps::all()` فجأة عند إدخال `marketplace_items`.** بدلها، تُعرَّف واجهة PHP واحدة `MarketplaceCatalogRepository` بتوقيعين (methods) بسيطين — `all()` و`find($key)` — ولها تطبيقان (Implementations) يتعايشان بنفس الوقت خلال فترة انتقالية:

```
                     MarketplaceCatalogRepository (interface)
                                    │
                  ┌─────────────────┴─────────────────┐
                  ↓                                     ↓
  StaticPlatformAppsRepository              DatabaseMarketplaceRepository
   (الحالي — يقرأ PlatformApps::all())        (الجديد — يقرأ جدول marketplace_items)
```

خطوات الانتقال العملية:
1. `DatabaseMarketplaceRepository` يُبنى ويُعبّى بنفس بيانات `PlatformApps::all()` بالضبط (تطابق حرفي) — لكن **`StaticPlatformAppsRepository` يبقى الفعّال فعليًا** (Container binding يشير له).
2. فترة تحقق تطابق (Parity Check): سكربت/اختبار يقارن ناتج التطبيقَين حرفيًا (كل حقل، كل عنصر) — لازم تطابق ١٠٠٪ قبل أي خطوة تالية.
3. فقط بعد تأكيد التطابق، يتحوّل الـ Binding للتطبيق الجديد (`DatabaseMarketplaceRepository`) — تبديل بسطر واحد بملف الخدمة (Service Provider)، قابل للتراجع الفوري (Rollback بسطر واحد أيضًا) لو ظهرت مشكلة.
4. `StaticPlatformAppsRepository` (والملف `PlatformApps.php` نفسه) يُحذَف فقط بعد فترة تشغيل مستقرة بالبيئة الجديدة — لا بنفس الدورة اللي أُدخِلت فيها.

هذا يضمن إن الـ Marketplace الحالي **ما ينكسر بأي لحظة انتقال**، وإن أي مشكلة غير متوقعة لها مسار رجوع فوري بلا Migration عكسية معقّدة.

1. **إنشاء `marketplace_items` وتعبئتها من `PlatformApps::all()`** عبر `DatabaseMarketplaceRepository` أعلاه — نفس الثمانية عناصر، نفس المحتوى بالضبط، بالتوازي مع النظام القديم لا بدلًا عنه فورًا.
2. **إضافة حقل `type`** — تُصنَّف الثمانية الحالية كلها `application` (دقيق فعليًا، ولا واحد منها Integration أو Service حاليًا).
3. **إنشاء `partners` بسجل واحد** ("حكم ورقم"، first-party) وربط كل العناصر الحالية به.
4. **إعادة تسمية/توسيع `app_subscriptions` → `subscriptions` بـ Polymorphic subscriber** — الاشتراكات الحالية (User فقط) تبقى صالحة تمامًا بدون أي فقدان بيانات، فقط العمود يصير أعم.
5. **إضافة `subscription_plans` + `plan_entitlements`** — كل تطبيق مجاني حالي ياخذ خطة وحيدة تلقائية "Free" بصلاحية كاملة — **صفر تغيير سلوك**، فقط تصحيح النموذج تحضيرًا للمستقبل.
6. **إضافة `subscription_seats`** — الجدول يُنشأ، **لا يُستخدم** لحد أول تطبيق Organization-level حقيقي.
7. **فقط عند الحاجة الفعلية لاحقًا:** Integrations، AI Layer، Partner Portal — تُبنى فوق أساس صحيح بالفعل، بلا أي إعادة هيكلة.

**بوابة معرفة نفسها:** التغيير الوحيد المتوقع هو استبدال `PlatformApps::all()` بـ Eloquent query على `marketplace_items` — لا تغيير على منطق الأنظمة، الحاسبة، أو المفضلة. **Core Platform (الهوية/المؤسسات/العضويات) لا تُمَس إطلاقًا بهذي المرحلة.**

---

# E. Implementation Phases

**Phase 0 (الآن):** اعتماد هذي الوثيقة. لا كود.

**Phase 1 — Required Now (بعد الاعتماد):**
`MarketplaceCatalogRepository` (Compatibility Layer) + `marketplace_items` (هوية مشتركة نحيفة فقط، بدون أي حقل خاص بنوع) + `partners` (بحقل `partner_type`) + `marketplace_categories`، بتعايش كامل مع `PlatformApps` الحالية لحد تأكيد التطابق (Zero behavior change) · إعادة تسمية `subscriptions` بـ Polymorphic subscriber · `access_assignments` (حتى بالحالة الفردية البسيطة — لا اختصار للسلسلة) · `subscription_plans`/`plan_entitlements` بخطة مجانية وحيدة لكل عنصر حالي.
*السبب إنها "الآن":* هذي التصحيحات الأساسية للنموذج — لو تأجّلت لحد ما تدخل الاشتراكات المدفوعة والتكاملات فعليًا، التكلفة تصير أعلى بكثير (بيانات حقيقية بالإنتاج تحتاج Migration معقّدة بدل تصميم صحيح من البداية). هذا بالضبط الخطر اللي حذّرت منه. لاحظ: `access_assignments` تدخل من Phase 1 نفسها رغم بساطة الاستخدام الحالي (اشتراكات فردية فقط) — لأن تأجيلها يعني لاحقًا Migration لبيانات إنتاج حقيقية بدل حقل يُضاف بجدول فاضي الآن.

**تحديث تسلسلي (2026-08-08):** التنفيذ الفعلي لـPhase 1 أعلاه **ينقسم لشريحتين متتاليتين غير متزامنتين** (تفصيل كامل بـ`marketplace-implementation-specification.md` قسم AB): **Phase 1a** = كتالوج فقط (`marketplace_items`/`partners`/`marketplace_categories` + Compatibility Layer)، بمعزل تام عن أي منطق اشتراك. **Phase 1b** = الباقي (`subscriptions`/`access_assignments`/`subscription_plans`/`plan_entitlements`/`audit_logs`)، **لا تبدأ قبل اعتماد نجاح 1a صراحة**. هذا تعديل تسلسل تنفيذي فقط (Sequencing) — لا يغيّر نطاق "Required Now" نفسه المحدَّد أعلاه.

**Phase 2 — عند أول تطبيق مدفوع أو Organization-level حقيقي:**
`subscription_seats` + منطق تعيين المقاعد + **Active Organization Context بالجلسة (Tenant Isolation)** + إلزام `organization_id`/`user_id` بجداول بيانات أي تطبيق جديد ينضم من هذي المرحلة فصاعدًا + طبقة Entitlement/Gate مفعّلة فعليًا بفحوصات صلاحية حقيقية تستعلم `AccessAssignment`.

**Phase 3 — عند أول Integration حقيقي:**
`integration_connections` + `integration_events` + Webhook Job Pipeline + `IntegrationMapperInterface`.

**Phase 4 — عند أول ميزة AI حقيقية مخطط لها فعليًا:**
`AIServiceInterface` وما تحتها (LLM Gateway، Model Selection، Prompt Versioning، Context Manager بقيد الصلاحيات + Active Organization Context معًا، Usage & Cost Tracking، Audit Logs بمصدر البيانات المُستخدَم) — **كل هذي الأبعاد تُبنى معًا من اليوم الأول لهذي المرحلة، لا تُضاف تدريجيًا بعدها**، لأن التدقيق بمنصة قانونية/مالية لا يصح يكون إضافة لاحقة.

**Phase 5 — عند فتح المنصة لشركاء خارجيين:**
Partner Portal + مسار المراجعة الكامل + إصدار API Keys/OAuth Clients محدودة النطاق.

**ما لا يُبنى الآن بأي مرحلة قريبة (Future-ready فقط، بلا جدول زمني):**
Reviews/Ratings · Media Gallery/Changelog كامل · Notifications/Files/Search الموحّدة/Audit Logs العامة/Payments/API Gateway العامة — تُبنى Just-In-Time لأول ميزة حقيقية تحتاجها كل واحدة، لا قبل.

### تنويه صريح: لا يوجد Billing Engine بأي مرحلة من هذي الخمسة
لاحظ إن **ولا مرحلة واحدة من ١-٥ فيها بناء نظام فوترة فعلي** (بوابة دفع، فواتير، تجديد تلقائي، ضريبة). المتاح فقط: حقول السعر ودورة الفوترة بـ`SubscriptionPlan` (قسم ٤) — بيانات وصفية جاهزة لتُقرأ من نظام فوترة حقيقي **يوم يوجد أول تطبيق مدفوع فعليًا**، لا قبل. هذا تطبيق مباشر لمبدأ Future-ready architecture ≠ Future-built software المذكور بأعلى الوثيقة: الجاهزية بالنموذج شيء، والبناء الفعلي (تكامل بوابة دفع سعودية مثل Moyasar/Tap، منطق Dunning، الفوترة الضريبية) قرار منفصل تمامًا يُتَّخذ لاحقًا بمعزل عن هذي الوثيقة.
