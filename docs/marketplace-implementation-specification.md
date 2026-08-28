# Hukm w Rakam — Marketplace Implementation Specification

**الحالة:** Specification فقط — بدون كود، بدون Migration، بدون تعديل أي ملف، بدون تنفيذ أي Feature.
**المراجع الملزمة (لا تُعاد صياغتها هنا، تُطبَّق):** `marketplace-architecture-blueprint.md` (Target Architecture) · `marketplace-product-ux-architecture.md` (Product/UX + قسم ٠ Terminology) · `marketplace-ui-design-system.md` (Design System + قسم ٠ Terminology) · `marketplace-high-fidelity-pilot.md` · `marketplace-high-fidelity-full.md`.
**الدور:** العقد التقني بين Product/UX/Architecture من جهة، وDatabase/Backend/Frontend/Admin/QA من جهة أخرى — قابل للتنفيذ حرفيًا بواسطة فريق Laravel.

**قاعدة الالتزام:** لا إعادة اختراع لأي قرار معتمد بـBlueprint. أي تعارض تقني حقيقي اكتُشف أثناء مطابقة القرارات بالكود الفعلي **لم يُحَل هنا** — سُجِّل بقسم AE (Architecture Conflicts) بصيغة: القرار الحالي / المشكلة / سبب التعارض / الخيارات / التوصية، بانتظار قرارك.

**قاعدة Core Boundary:** لا تعديل لأي جزء من Core Platform الحقيقي (Identity، الدليل القانوني، نظام الاستشارات، هوية حكم ورقم). أي حاجة فعلية لتعديل بـCore تُسجَّل بقسم AF (Core Dependencies)، لا تُنفَّذ ضمنيًا.

---

# Architecture Decisions — Final (AD-001 – AD-005)

**معتمدة رسميًا (2026-08-08).** هذي القرارات حسمت التعارضات المسجَّلة أصلًا بقسم AE (AC-001/002/003) + قرار جديد (AD-005). **نفس النص مُضاف حرفيًا لـ`marketplace-architecture-blueprint.md`** (المرجع المعماري الأساسي) — موجود هنا أيضًا لأن هذي الوثيقة التنفيذية يجب تُقرأ بمعزل قابل للتنفيذ الكامل بلا رجوع دائم للوثيقة الأخرى.

- **AD-001 (Audit Minimal):** استثناء ضيّق على "Audit JIT" — Audit Trail محدود (Append-only، بلا واجهة) من Phase 1 لثمانية أحداث فقط: Subscription Created/Activated/Cancelled/Suspended، Access Assigned/Revoked، Seat Assigned/Released. لا منصّة تدقيق كاملة.
- **AD-002 (Polymorphic Subscriber، مشروط):** الإبقاء على AC-001 كما هو، بخمسة ضوابط إلزامية: `subscriber_type` Enum مغلق (user/organization فقط)، إنشاء حصري عبر Domain/Service Layer (لا `Subscription::create()` مباشر)، قيود DB حيثما أمكن، Repository/Resolver موحّد، اختبارات شاملة (تفصيل كامل بقسم G/T أدناه).
- **AD-003 (Concurrent Seat Allocation):** Transaction + `lockForUpdate()` + تحقق من جهة الخادم حصرًا + قيود DB مساندة + اختبار Concurrency صريح (لا Unit Test عادي فقط).
- **AD-004 (Organization Context Dependency):** Phase 1 لا يعتمد على Active Organization Context. لا عمل Organization-level (اشتراكات مؤسسية/مقاعد/وصول مؤسسي) قبل توفّره فعليًا (يطابق CD-001 بقسم AF حرفيًا).
- **AD-005 (Entitlement ≠ Authorization، جديد):** Entitlement = "مؤهَّل لاستخدام العنصر؟" (Marketplace/Core). Authorization = "يقدر يفعل وش بالضبط بداخله؟" (كل تطبيق بمفرده). السلسلة الرسمية: `Authentication → Organization Context → Entitlement → Application Access → Authorization → Action`. لا دمج بين الاثنين أبدًا.
- **AD-006 (Legacy Source Migration Boundary، معتمد بعد Phase 1b):** `app_subscriptions`/`AppSubscription` (Core Platform Phase 1) مصنَّف رسميًا Legacy/Migration Source فقط — ممنوع أي كود جديد يعتمد عليه ابتداءً من الآن. `marketplace:backfill-free-access` أداة ترحيل لمرة واحدة، ليست مسار تشغيل دائم. لا حذف ولا كتابة متبادلة.
- **AD-007 (Marketplace Access ≠ Core Content Visibility، معتمد بعد Phase 1b):** وجود Entitlement لعنصر Marketplace لا يعني تلقائيًا تفعيل قيد دخول على محتوى التطبيق الأصلي — سياسة ظهور المحتوى قرار منتجي مستقل بكل تطبيق. بوابة معرفة مثال حي: تبقى عامة رغم وجود `Subscription`/`AccessAssignment` حقيقيَين لها.
- **AD-008 (SubscriptionSeat كيان مستقل، معتمد بعد تصميم Phase 2):** `SubscriptionSeat` جدول مستقل، لا يُشتَق من عدّ `AccessAssignment` — Seat=تخصيص إداري، Access=صلاحية استخدام فعلية، قد تنفصلان (مقعد مخصَّص + وصول معلَّق حالة صالحة).
- **AD-009 (Audit Events منفصلة، معتمد بعد تصميم Phase 2):** `SeatAssigned`/`SeatReleased` (مؤسسي) منفصلتان دلاليًا عن `AccessGranted`/`AccessRevoked` (شخصي) — لا حدث خامس جديد، توضيح لـAD-001.
- **AD-010 (ترتيب Phase 2 الداخلي، معتمد بعد تصميم Phase 2):** 2A (Active Organization Context) → 2B (Organization Subscription) → 2C (Seat Management) → 2D (Organization Access) → 2E (Organization Authorization) — لا طبقة تبدأ قبل نجاح واعتماد التي قبلها.
- **AD-011 (Context ≠ Authorization، معتمد بعد تصميم Phase 2):** اختيار مؤسسة نشطة لا يمنح تلقائيًا أي صلاحية إدارية بها — الصلاحيات تُحسَب دائمًا من `Membership.role` الحقيقي بالسياق النشط تحديدًا. السلسلة: `Authenticated User → Active Organization Context → Membership → Organization Role → Entitlement → Application Access → Application Authorization`.
- **AD-012 (Active Organization Context = Pointer فقط، معتمد بعد Phase 2A):** الجلسة تجاوب "أي مؤسسة يعمل ضمنها الآن؟" فقط — ليست مصدر حقيقة لأي قرار وصول/عزل. كل قرار حسّاس (Subscription/Seat/Access مؤسسي) يعيد التحقق من `Membership` بقاعدة البيانات مباشرة عند نقطة الفعل نفسها، لا يكتفي بالثقة بالسياق النشط. Middleware Phase 2A تبسيط تجربة مستخدم، لا بديل عن هذا التحقق المستقل.
- **AD-013 (Single Source of Truth for Effective Marketplace Access، معتمد بعد Access Control Audit):** أي تعريف لـ"الوصول الفعّال" لعنصر Marketplace مصدره `EntitlementResolver` حصرًا — ممنوع أي Controller/View يعيد بناء نفس المنطق باستعلام مستقل موازٍ. لا يمس فصل Entitlement/Ownership/Authorization الثلاثي (يبقى كما هو) — يخص فقط تكرار تعريف نفس سؤال Entitlement بمكانين. تكرار حقيقي مكتشَف بالتدقيق (`MyAppsController`) غير مُصلَح بعد، مسجَّل كدين تقني.
- **AD-014 (Explicit User Intent Must Be Preserved، معتمد بعد Legacy Closure Plan):** أي Migration/Backfill/Sync لا يجوز يُنشئ أو يُعيد تفعيل Access/Subscription لمستخدم إلغاه صراحة، إلا بفعل إعادة تفعيل صريح منه أو من مسؤول مخوَّل. `Inactive ≠ Never Subscribed`، `Cancelled ≠ Never Activated` — الفحص الصحيح دومًا "وجود أي سجل بأي حالة" لا "غياب حالة فعّالة حاليًا". خطر مؤكَّد بالتجربة الفعلية بأمر `marketplace:backfill-free-access` — راجع `legacy-subscription-closure-plan.md` قسم 4 و`legacy-subscription-l1-spec.md`.
- **AD-015 (Marketplace Integrates Into Hukm w Rakam، Not The Reverse، معتمد بعد L1 + Dashboard Transition Decision):** النص المرجعي الرسمي (حرفي، معتمد من المستخدم): *"Hukm w Rakam is an existing, live production platform. The Marketplace is an additive capability integrated into Hukm w Rakam. It is not a replacement, rebrand, redesign, or architectural takeover of the existing Core Platform. No existing Core Platform behavior, navigation, identity, or user flow may be changed unless that change is explicitly approved as part of a separate scope."* قيد دائم يُطبَّق على كل Prompt/Spec قادم بلا استثناء. شقّان: **(١) الاتجاه:** Marketplace وحدة تُضاف فوق حكم ورقم الحالية — لا تُعاد هندسة هويتها/Navigation/سلوك خدماتها القائمة بسبب Marketplace؛ أي نقطة دمج مستقبلية (Header، Dashboard كـUnified Home) إضافية بحتة، لا استبدال. **(٢) الفصل بين تحديد الاتجاه والتنفيذ:** وثيقة تُثبِّت Target Architecture لنقطة دمج مستقبلية (مثال: `dashboard-marketplace-transition-decision.md`) لا تُشكِّل إذنًا ببدء تنفيذها — إذنان منفصلان دائمًا، نفس بوّابة Design→Implementation بكل مرحلة سابقة. الأثر العملي الملزم: أي عمل مستقبلي بنقطة دمج يعيد استخدام `EntitlementResolver`/My Apps (AD-013) لا منطق وصول موازٍ خاص به، ويحافظ حرفيًا على Navigation/هوية حكم ورقم الحالية.

- **AD-016 (Organization Membership Domain Changes Must Be Auditable، معتمد بعد Phase OI):** كل تغيير Domain حساس بعضوية مؤسسة (إضافة/إزالة عضو، تغيير Role، Transfer Ownership) يجب يكون قابلًا للتدقيق قبل اعتبار Organization Lifecycle مكتملًا — **غير مُغلَق بعد**، `MembershipService` (Phase OI) نفَّذ Last Owner Rule/Transfer Ownership بلا أي حدث Audit مرافق (خارج نطاقها وقتها). يُعالَج بـPhase OL أو مرحلة Audit مستقلة لاحقة، لا يُفترَض حلّه ضمنيًا بأي مرحلة بلا تصريح صريح يذكره.

- **AD-017 (Ownership Transfer Requires a Real Owner; Platform Staff Has No Exception Once One Exists، معتمد بعد Security Review #2 لـPlatform Authorization Foundation):** لو مؤسسة لها Owner حقيقي بالفعل، أي فعل يمنح/ينقل/يُنشئ Owner جديدًا عليها — أي تابع، أي مصدر استدعاء — لا يجوز إلا لفاعل هو نفسه Owner حقيقي بتلك المؤسسة؛ Platform Staff **بلا استثناء** بمجرد وجود Owner حقيقي واحد. الاستثناء الوحيد الباقي: مؤسسة بلا Owner حقيقي إطلاقًا (تأسيس أول Owner، ثابت منذ Option D). اكتُشف فعليًا (تنفيذ حقيقي، لا نظري) أن `transferOwnership()` بقيت خارج نفس القاعدة المطبَّقة على `add()`/`changeRole()` بعد Hardening Pass الأول، متيحةً مسارًا بديلًا بخطوتين لنفس النتيجة المحظورة — راجع `docs/platform-authorization-security-review-2.md` Finding H1. **الأثر الملزم الدائم:** أي مسار (حالي أو مستقبلي) ينتج عنه `Membership.role=Owner` يمر عبر نقطة تحقق مركزية واحدة (`authorizeGrantingOwnership()`)، لا تكرار منطق مشابه بمكان مختلف — درس مُعمَّم بعد اكتشافه ثلاث مرات متتالية بنفس المرحلة.

- **AD-018 (Archived Organization Cannot Receive New or Expanded Marketplace Access، معتمد بعد Adversarial End-to-End Review لـOrganization Lifecycle + Authorization):** مؤسسة `archived` لا يجوز تكتسب Marketplace Access جديدًا/مُوسَّعًا (اشتراك جديد، مقعد جديد، توسعة حد مقاعد) — قيد **Domain State** منفصل عن Authorization، لا يتجاوزه وجود Staff/Owner. اكتُشف فعليًا: `OrganizationSubscriptionService::create()` لا تتحقق من `isArchived()` — اشتراك جديد نشط + مقعد يمنحان وصولًا حقيقيًا مؤكَّدًا عبر `EntitlementResolver` لمؤسسة "مؤرشَفة". راجع `docs/organization-lifecycle-authorization-e2e-security-review.md` Finding E1. **الفرق المُثبَّت:** `Authorization` (من يقدر؟) مقابل `Domain State` (هل الحالة تسمح أصلًا؟) — فئتا تحقق مستقلتان، إلزاميتان لأي Lifecycle مستقبلي. **🟢 مُغلَقة نهائيًا (2026-08-17)** — `Membership` **ليست** Marketplace Access (قرار نهائي، لا تستدعي الـGuard). Race Condition نظري مع `archive()` أُصلِح (`create()` تقفل صف المؤسسة قبل الفحص، راجع `docs/ad-018-race-condition-fix-completion-report.md`)، ومراجعتان مستقلتان لاحقتان أكَّدتا الإغلاق (`docs/ad-018-security-review.md`/`-2.md`). ملاحظة Race إضافية بـ`changeSeatLimit()`/`assign()` حُلِّلت وثبت أنها ذاتية التصحيح (`cancel()` تُعيد استعلام المقاعد النشطة طازجًا) — لا كود إضافي، قرار موثَّق (`docs/ad-018-seat-changeseatlimit-race-analysis.md`).

**كل قسم أدناه يعكس هذي القرارات الثمانية عشر كأمر واقع — لا "توصية معلَّقة" بعد الآن.**

---

# A. Current Code Inventory

**مصدر الحقيقة: قراءة فعلية للكود بتاريخ هذي الوثيقة، لا الذاكرة ولا الوثائق السابقة.**

### Stack
| المكوّن | الإصدار الفعلي (من `composer.json`) |
|---|---|
| PHP | ^8.3 |
| Laravel | ^13.8 |
| Filament | ^3.3 |
| Livewire | ^3.8 |
| Laravel Breeze | ^2 (Auth scaffolding) |
| قاعدة البيانات المحلية | SQLite (بيئة تطوير) |

### Models (`app/Models`)
| Model | الجدول | ملاحظة |
|---|---|---|
| `User` | `users` | Breeze قياسي + `memberships()`, `organizations()`, `subscriptions()`, `hasActiveSubscription(string $appKey): bool` |
| `Organization` | `organizations` | `owner()`, `memberships()` |
| `Membership` | `memberships` | `role` مُلقَّم (cast) لـ`App\Enums\MembershipRole` |
| `AppSubscription` | `app_subscriptions` | `user()`, scope `active()` |
| `ServiceInterest` | `service_interests` | بلا علاقات — نموذج تسجيل اهتمام مسطّح |
| `Category`, `LawEntry`, `LawArticle`, `LegalUpdate` | جداول بوابة معرفة | **خارج نطاق Marketplace بالكامل — Core Platform محتوى، لا تُمَس** |

### Migrations ذات الصلة المباشرة بالـMarketplace (المصدر الحقيقي للـSchema الحالي)
```
organizations:      id, name, type(string,default:'individual'), owner_id(FK users,nullable,nullOnDelete), timestamps
memberships:        id, user_id(FK,cascade), organization_id(FK,cascade), role(string), timestamps
                     UNIQUE(user_id, organization_id)
app_subscriptions:  id, user_id(FK,cascade), app_key(string), status(string,default:'active'),
                     subscribed_at(timestamp,nullable), timestamps
                     UNIQUE(user_id, app_key)
service_interests:  id, service_key(string), service_name(string), email(string), timestamps
```
**ملاحظات حرجة للـSpecification:** لا `soft deletes` بأي جدول. لا `organization_id` بـ`app_subscriptions` (شخصي حصرًا، مطابق تمامًا لما وثّقه Blueprint قسم A). لا FK حقيقي من `service_interests.service_key` أو `app_subscriptions.app_key` لأي جدول (كلاهما نص حر يطابق مفاتيح `PlatformApps` يدويًا).

### Controllers
`MarketplaceController` (index/show، يقرأ `PlatformApps::all()` مباشرة، يحسب `subscribed` عبر `Auth::user()->hasActiveSubscription()`) · `DashboardController` (يستدعي `FreeAppProvisioner::ensure()`، يعرض `subscribedApps` + `memberships`) · `ServiceInterestController` (store فقط) · `HomeController`, `LawController`, `LegalUpdateController`, `BookmarkController`, `ProfileController`, `PlatformController` — **كلها Core Platform/بوابة معرفة، لا تُمَس**.

### Livewire
مكوّن واحد فقط: `App\Livewire\GratuityCalculator` (حاسبة، Route كامل الصفحة) — **لا علاقة بالـMarketplace**، لا Livewire مستخدَم حاليًا بأي شاشة Marketplace (Blade + Controllers تقليدية بالكامل).

### Filament Resources
`OrganizationResource` (+RelationManager للأعضاء) · `AppSubscriptionResource` (نموذج/جدول بسيط، يقرأ `PlatformApps::all()` لخيارات `app_key`) · `LawEntryResource`, `CategoryResource`, `LegalUpdateResource`, `ServiceInterestResource` (محتوى بوابة معرفة/Leads). كلها تحت `AdminPanelProvider` **بانل واحد فقط** (`id('admin')`, `path('admin')`), بلا Multi-tenancy Filament مفعَّلة.

### Authorization (حالة حرجة للـSpecification)
**لا `Policies` موجودة إطلاقًا (المجلد فارغ). لا `Gates` مسجَّلة (`AppServiceProvider::boot()` فارغ تمامًا). لا `AuthServiceProvider` مخصَّص.** كل تحكّم وصول حاليًا Ad-hoc داخل الـController (`Auth::user()->hasActiveSubscription($key)`) أو غائب تمامًا بلوحة Filament (أي مستخدم Filament مصادَق عليه يقدر يعدّل أي `Organization`/`AppSubscription` — **لا Policy تقيّده**). هذي حقيقة مهمة لقسم G/Y أدناه.

### Enums
`App\Enums\MembershipRole` (backed string enum، ٨ قيم رسمية، `label()` + `options()`) — النمط المعتمد لكل Enum جديد بالمشروع.

### Support/Services
`App\Support\PlatformApps` (مصفوفة PHP ثابتة، ٨ عناصر، مصدر الحقيقة الحالي الوحيد للكتالوج) · `App\Support\FreeAppProvisioner::ensure(User $user)` (يزوّد تلقائيًا `AppSubscription` لكل عنصر `available && free`، Idempotent عبر `firstOrCreate`).

### Blade Components ذات صلة
`x-service-card` (Application variant فقط اليوم) · `x-service-icon` · `x-interest-modal` · `x-status-badge` · Layouts: `x-platform-layout` (`app/View/Components/PlatformLayout.php`)، `x-app-layout`, `x-guest-layout`.

### Routes (كامل، من `routes/web.php`)
```
GET  /                          platform.home            (عام)
GET  /marketplace                platform.marketplace     (عام)
POST /marketplace/interest       service-interest.store   (عام)
GET  /marketplace/{key}          platform.marketplace.show (عام)
GET  /marefa                     marefa.home               (عام، يستدعي FreeAppProvisioner لو مسجَّل)
GET  /laws, /laws/{lawEntry}     laws.index/show            (عام)
GET  /updates                    updates.index              (عام)
GET  /calculators/gratuity       calculators.gratuity       (عام، Livewire)
GET  /dashboard                  dashboard                  (auth)
GET|PATCH|DELETE /profile        profile.*                  (auth)
POST /laws/{lawEntry}/bookmark   bookmarks.toggle            (auth)
GET  /bookmarks                  bookmarks.index             (auth)
+ routes/auth.php (Breeze قياسي)
```
**لا Middleware مخصَّص واحد بالمشروع (`app/Http/Middleware` فارغ عدا الافتراضي).**

### Tailwind/Design Tokens
مؤكَّد سابقًا بـ`marketplace-ui-design-system.md` قسم ٤/٣٩ — `tailwind.config.js` هو المصدر، لم يتغيّر.

---

# B. Architecture Mapping

جدول تحويل مباشر: كيان Target Architecture (Blueprint) ← الحالة الحالية بالكود ← القرار التنفيذي:

| كيان Blueprint | الحالة الحالية | القرار |
|---|---|---|
| `MarketplaceItem` | لا يوجد (مصفوفة `PlatformApps`) | جدول جديد + Compatibility Layer (قسم K) |
| `Partner` | لا يوجد | جدول جديد، Required Now |
| `MarketplaceCategory` | لا يوجد (`Category` الموجود خاص ببوابة معرفة فقط، **لا علاقة**) | جدول جديد منفصل تمامًا عن `categories` الحالي — **لا إعادة استخدام**، تجنّبًا لخلط سياقي (تصنيفات قانونية ≠ تصنيفات كتالوج) |
| `Subscription` (Polymorphic) | `app_subscriptions` (`user_id` مباشر فقط) | إعادة تسمية/توسيع (قسم C) |
| `SubscriptionPlan`, `PlanEntitlement` | لا يوجد | جداول جديدة، Required Now (بحد أدنى: خطة مجانية واحدة لكل عنصر) |
| `AccessAssignment` | لا يوجد | جدول جديد، Required Now |
| `SubscriptionSeat` | لا يوجد | جدول جديد، بنية فقط Phase 1 (Future-ready)، استخدام فعلي Phase 2 |
| Active Organization Context | لا يوجد (لا جلسة، لا Middleware) | **Core Dependency CD-001** — خارج نطاق تنفيذ Marketplace المباشر |
| `integration_connections`, `integration_events` | لا يوجد | Future-ready، غير مبني |
| `service_requests` (مشتق من Service Lifecycle UX، غير مُسمّى صراحة بـBlueprint) | لا يوجد | إضافة نمذجة جديدة، مبرَّرة بقسم N — **ليست تعارضًا**، Blueprint لم يستبعدها، فقط لم يسمّها |
| `audit_logs` | لا يوجد | راجع **AC-003** — تعارض مسجَّل، لا قرار تلقائي |

---

# C. Database Specification

## Required Now (Phase 1)

### `marketplace_items`
| الحقل | النوع | Nullable | Default | ملاحظة |
|---|---|---|---|---|
| `id` | bigint PK | لا | — | |
| `key` | string(100) | لا | — | **UNIQUE**، slug، يطابق مفاتيح `PlatformApps` الحالية حرفيًا أثناء التعايش |
| `type` | string(20) | لا | `'application'` | enum تطبيقي: application/integration/service (PHP backed enum، لا DB enum — يطابق نمط `MembershipRole` الحالي) |
| `partner_id` | FK→`partners.id` | لا | — | `restrictOnDelete` (لا يُحذَف شريك له عناصر) |
| `category_id` | FK→`marketplace_categories.id` | نعم | null | `nullOnDelete` |
| `name`, `tagline`, `description` | string/string/text | لا/لا/لا | — | |
| `icon` | string(50) | لا | — | يطابق مفاتيح `x-service-icon` الحالية |
| `status` | string(20) | لا | `'published'` | Lifecycle (قسم U) |
| `billing_model` | string(20) | لا | `'user_only'` | user_only/organization_only/both |
| `pricing_model` | string(20) | لا | `'free'` | free/paid/freemium |
| `compatibility` | JSON | نعم | null | نفس شكل `audiences` الحالي حرفيًا |
| `version` | string(10) | لا | `'1.0'` | حقل فاضٍ جاهز (Blueprint قسم ٣) |
| `created_at`, `updated_at` | timestamp | — | — | |
**Indexes:** `(type, status)` مركّب (فلترة Catalog الشائعة) · `partner_id` · `category_id`.
**Soft Delete:** لا — الحذف الفعلي لعنصر كتالوج يجب يمر بـLifecycle (`deprecated`) لا حذف DB، لتفادي فقدان مرجعية `Subscription`/`AccessAssignment` القائمة.
**Audit:** راجع AC-003.
**Tenant Scope:** **Global** (الكتالوج نفسه غير مرتبط بمؤسسة، مؤكَّد بقسم D بوثيقة الـUX).
**علاقات:** `belongsTo(Partner)`, `belongsTo(MarketplaceCategory)`, `hasOne(ApplicationDetail)` (لو `type=application`)، `hasMany(Subscription)`.

### `application_details` / `integration_details` / `service_details`
نمط Class Table Inheritance (Blueprint قسم ١) — **`application_details` فقط يُبنى فعليًا الآن** (النوع الوحيد الحقيقي):

| `application_details` | النوع | Nullable | ملاحظة |
|---|---|---|---|
| `id` | PK | لا | |
| `marketplace_item_id` | FK→`marketplace_items.id`, UNIQUE | لا | علاقة 1:1، `cascadeOnDelete` |
| `entry_route` | string(255) | نعم | اسم Route حقيقي (مثال: `marefa.home`) |

`integration_details` و`service_details`: **Future-ready but Not Built** — الشكل موثَّق فقط (Blueprint قسم ١: `auth_type`/`webhook_url` لـIntegration، `delivery_type` لـService)، لا Migration تُكتَب لهما بـPhase 1.

### `partners`
| الحقل | النوع | Nullable | Default |
|---|---|---|---|
| `id` | PK | لا | — |
| `name` | string | لا | — |
| `partner_type` | string(30) | لا | `'first_party'` |
| `revenue_share_percentage` | decimal(5,2) | نعم | null |
| `created_at`/`updated_at` | timestamp | — | — |
**Tenant Scope:** Global. **Seed Phase 1:** سجل واحد ("حكم ورقم"، `first_party`)، كل عناصر `marketplace_items` الحالية الثمانية تُربَط به.

### `marketplace_categories`
| `id`, `name`, `slug` (UNIQUE), `created_at`/`updated_at` | — بنية بسيطة مقصودة، لا حقول إضافية الآن.
**Tenant Scope:** Global.

### `subscriptions` (إعادة تسمية/توسيع `app_subscriptions`)
| الحقل | النوع | Nullable | Default | ملاحظة |
|---|---|---|---|---|
| `id` | PK | لا | — | |
| `subscriber_type` | string(20) | لا | `'user'` | Polymorphic morph type — راجع **AC-001** |
| `subscriber_id` | bigint (morph id) | لا | — | راجع **AC-001** |
| `marketplace_item_id` | FK→`marketplace_items.id` | لا | — | **يستبدل `app_key` النصي** |
| `subscription_plan_id` | FK→`subscription_plans.id` | لا | — | |
| `status` | string(20) | لا | `'active'` | active/cancelled/past_due (Blueprint قسم ٤) |
| `subscribed_at` | timestamp | نعم | null | يطابق العمود الحالي حرفيًا |
| `created_at`/`updated_at` | timestamp | — | — | |
**Indexes:** `(subscriber_type, subscriber_id)` مركّب · `marketplace_item_id`.
**Unique:** `(subscriber_type, subscriber_id, marketplace_item_id)` — يمنع اشتراك مكرَّر لنفس الفاعل بنفس العنصر (يعمّم القيد الحالي `(user_id, app_key)`).
**Tenant Scope:** **User-scoped** لو `subscriber_type=user`، **Organization-scoped** لو `=organization` — لا يظهر لمستخدم بمؤسسة أخرى بأي حال (قسم H).
**CHECK Constraint (يُنفَّذ وقت الكتابة الفعلية، ليس هنا):** `subscriber_type IN ('user','organization')` فقط — يمنع أي قيمة ثالثة غير متوقَّعة تخترق نمط الـPolymorphic بصمت.

### `subscription_plans`
| `id`, `marketplace_item_id`(FK), `name`, `seat_limit`(int,nullable=بلا حد), `price`(decimal,nullable), `billing_cycle`(string,nullable), timestamps |
**Seed Phase 1:** خطة "Free" واحدة تلقائية لكل عنصر حالي `pricing_model=free` (٦ من الثمانية اليوم مؤهَّلة، الاثنان الباقيان "قريبًا" بلا خطة حتى الإطلاق).
**Tenant Scope:** Global (الخطة خاصية العنصر، لا المؤسسة).

### `plan_entitlements`
| `id`, `subscription_plan_id`(FK), `feature_key`(string), `value`(string,nullable — رقم/`unlimited`/`true`), timestamps |
**Phase 1:** لا يُملأ فعليًا لبوابة معرفة (لا ميزات مقيَّدة داخل التطبيق الوحيد الحقيقي اليوم) — الجدول يُبنى فارغًا، جاهزًا.

### `access_assignments`
| الحقل | النوع | Nullable | Default |
|---|---|---|---|
| `id` | PK | لا | — |
| `user_id` | FK→`users.id` | لا | — |
| `subscription_id` | FK→`subscriptions.id` | لا | — |
| `status` | string(20) | لا | `'active'` |
| `granted_at` | timestamp | لا | `now()` |
| `revoked_at` | timestamp | نعم | null |
**Unique:** `(user_id, subscription_id)`. **Tenant Scope:** User-scoped مباشرة، لكن يرث سياق Organization من `subscription_id` المرتبط (H). **Phase 1:** يُنشأ تلقائيًا 1:1 مع أي اشتراك شخصي (Blueprint قسم ٤) — لا استخدام فعلي لحالة "منفصلة عن الاشتراك" قبل Phase 2 (تعليق فردي لا يزال ممكنًا تقنيًا من اليوم الأول رغم غياب واجهة إدارية له).

### `subscription_seats` — **بنية فقط، بلا استخدام فعلي (Future-ready but Not Built فعليًا كوظيفة، الجدول نفسه Required Now بالبنية)**
| `id`, `subscription_id`(FK), `user_id`(FK), `assigned_at`, timestamps | UNIQUE`(subscription_id, user_id)` |
**السبب لإنشائه الآن رغم عدم الاستخدام:** تفاديًا لـMigration على بيانات إنتاج حقيقية لاحقًا (نفس منطق Blueprint قسم E، Phase 1).

### `audit_logs` (Required Now — AD-001، استثناء ضيّق ومحدَّد)
| الحقل | النوع | Nullable | Default | ملاحظة |
|---|---|---|---|---|
| `id` | bigint PK | لا | — | |
| `organization_id` | FK→`organizations.id` | **نعم** | null | فارغ للأحداث الشخصية البحتة (اشتراك مستخدم فردي بلا سياق مؤسسة) |
| `actor_user_id` | FK→`users.id` | لا | — | المستخدم المنفِّذ فعليًا (لا "النظام" كقيمة مجهولة — حتى `FreeAppProvisioner` التلقائي يسجَّل باسم المستخدم المستفيد نفسه) |
| `event` | string(50) | لا | — | Enum مغلق بالثمانية أحداث فقط (AD-001) — لا قيمة حرة |
| `subject_type` | string(50) | لا | — | `Subscription` / `AccessAssignment` / `SubscriptionSeat` فقط |
| `subject_id` | bigint | لا | — | |
| `metadata` | JSON | نعم | null | سياق إضافي مختصر (مثال: قيمة الحالة قبل/بعد) — **لا بيانات شخصية حساسة إضافية غير ضرورية** |
| `created_at` | timestamp | لا | `now()` | **لا `updated_at` بهذا الجدول إطلاقًا** — غياب العمود نفسه قيد بنيوي يفرض عدم التعديل، لا مجرد اتفاق |
**Indexes:** `(organization_id, created_at)` · `(subject_type, subject_id)` · `actor_user_id`.
**Soft Delete:** **لا — ولا حذف نهائي أيضًا.** لا `DeleteAction` بأي واجهة إدارية مستقبلية لهذا الجدول.
**إنفاذ Append-only على مستوى الكود (لا اتفاق إجرائي فقط):** الـModel المقابل (`AuditLog`) يُلغي (override) توابع `update()`/`delete()` ليرمي استثناء دائمًا — أي محاولة تعديل/حذف من أي طبقة بالتطبيق تفشل فورًا، لا تعتمد على "عدم بناء واجهة تعديل" وحده كضمان.
**Tenant Scope:** Organization-scoped جزئيًا (`organization_id` لتصفية استعلامات لاحقة لو احتيجت)، لكن **لا واجهة استعلام أصلًا بـPhase 1** — الجدول يُكتَب إليه فقط، لا يُقرأ منه إلا يدويًا وقت الحاجة (SQL مباشر أو Tinker، لا Route/Controller مخصَّص).
**من يكتب إليه:** حصرًا `SubscriptionService` (قسم F/D) — لا كتابة مباشرة من أي مكان آخر، نفس مبدأ "نقطة دخول واحدة" المطبَّق على `Subscription::create()` بـAD-002.

## Future-ready but Not Built (لا Migration تُكتَب لها بـPhase 1)
`integration_details`, `service_details`, `integration_connections`, `integration_events`, `service_requests` (قسم N)، أي جدول AI (قسم P).

---

# D. Domain Models

| Model جديد | يقابل | علاقات رئيسية |
|---|---|---|
| `MarketplaceItem` | `marketplace_items` | `belongsTo(Partner)`, `belongsTo(MarketplaceCategory)`, `hasOne(ApplicationDetail)`, `hasMany(Subscription)`, `hasMany(SubscriptionPlan)` |
| `ApplicationDetail` | `application_details` | `belongsTo(MarketplaceItem)` |
| `Partner` | `partners` | `hasMany(MarketplaceItem)` |
| `MarketplaceCategory` | `marketplace_categories` | `hasMany(MarketplaceItem)` |
| `Subscription` | `subscriptions` | `morphTo('subscriber')`, `belongsTo(MarketplaceItem)`, `belongsTo(SubscriptionPlan)`, `hasOne(AccessAssignment)` (شخصي) أو `hasMany(SubscriptionSeat)` (مؤسسي) |
| `SubscriptionPlan` | `subscription_plans` | `belongsTo(MarketplaceItem)`, `hasMany(PlanEntitlement)` |
| `PlanEntitlement` | `plan_entitlements` | `belongsTo(SubscriptionPlan)` |
| `AccessAssignment` | `access_assignments` | `belongsTo(User)`, `belongsTo(Subscription)` |
| `SubscriptionSeat` | `subscription_seats` | `belongsTo(Subscription)`, `belongsTo(User)` |

**تعديل Model موجود (لا حذف):** `User::subscriptions()` يبقى بنفس الاسم، لكن يشير للجدول الجديد بعد Cutover (قسم K) — لا تغيير بالتوقيع العام، `hasActiveSubscription()` تبقى، منطقها الداخلي يُعاد توجيهه لـ`AccessAssignment` بدل `AppSubscription.status` مباشرة (يطابق تنويه "Subscription ≠ Access" حرفيًا).

**`#[Fillable]`/`#[Hidden]` Attributes:** كل Model جديد يلتزم بنفس نمط PHP Attributes المستخدَم حاليًا (`User`, `Organization`, إلخ) — لا `protected $fillable` تقليدي.

**قيد إلزامي على `Subscription` (AD-002، نقطة ٢):** لا استدعاء مباشر لـ`Subscription::create()`/`Subscription::update()` من أي `Controller`, `Livewire Component`, أو `Filament Resource` بأي مكان بالكود. **نقطة الدخول الوحيدة المسموحة:** `SubscriptionService` (قسم F) — يضمن كل إنشاء/تعديل يمر بنفس التحقق (Enum صحيح، اتساق Plan/Item) ونفس تسجيل Audit (AD-001) بمكان واحد، لا منطق مكرَّر بأكثر من نقطة دخول. هذا القيد **معماري لا يُنفَّذ بقيد DB وحده** — يُراجَع بـCode Review لكل PR يلمس `Subscription`.

---

# E. Repository Architecture

`MarketplaceCatalogRepository` (Interface، Blueprint قسم D):
```
interface MarketplaceCatalogRepository {
    all(): Collection
    find(string $key): ?array
}
```
**تطبيقان:**
1. `StaticPlatformAppsRepository` — Wrapper رفيع فوق `PlatformApps::all()` الحالي حرفيًا (**الفعّال افتراضيًا عند بداية Phase 1**).
2. `DatabaseMarketplaceRepository` — يقرأ `MarketplaceItem::with(['partner','category','applicationDetail'])`، يحوّل النتيجة لنفس Shape المصفوفة الحالية (توافق حرفي مع كل Blade/View يستهلك `$app['key']`, `$app['name']`, إلخ — **صفر تغيير على طبقة العرض وقت التبديل**).

**الربط (Binding):** مزوّد خدمة جديد `App\Providers\MarketplaceServiceProvider` (وليس `AppServiceProvider` الحالي الفارغ — فصل واضح لمسؤوليات Marketplace عن باقي التطبيق، أنسب من تكديس `AppServiceProvider` الموجود). سطر واحد يحدد التطبيق الفعّال:
```
$this->app->bind(MarketplaceCatalogRepository::class, StaticPlatformAppsRepository::class);
```
**التبديل لاحقًا (Cutover):** تغيير هذا السطر فقط لـ`DatabaseMarketplaceRepository::class` — لا تغيير بأي مكان آخر بالكود (Controllers تعتمد على الـInterface عبر Dependency Injection، لا على التطبيق مباشرة).

---

# F. Service Layer

| الخدمة | الغرض | الحالة |
|---|---|---|
| `SubscriptionService` | **نقطة الدخول الوحيدة** لإنشاء/تفعيل/إلغاء/تعليق `Subscription` (AD-002 نقطة ٢) — كل تابع يكتب سجل `AuditLog` مطابق داخل نفس المعاملة | **جديد، Required Now** |
| `EntitlementResolver` | القرار المركزي "هل يقدر هذا المستخدم يستخدم هذا العنصر؟" (قسم G) — **Entitlement فقط، لا Authorization (AD-005)** | **جديد، Required Now** |
| `AuditLogger` | كتابة سجلات `audit_logs` (الثمانية أحداث، AD-001) — يُستدعى حصرًا من `SubscriptionService`/`SeatAssignmentService` | **جديد، Required Now (AD-001)** |
| `FreeAppProvisioner` (موجود، يُوسَّع لا يُستبدَل) | بدل إنشاء `AppSubscription` مباشرة، يستدعي `SubscriptionService::createFreeSubscription()` — لا `Subscription::create()` مباشر (AD-002) | مُوسَّع |
| `SeatAssignmentService` | تعيين/سحب مقعد ضمن Transaction+`lockForUpdate()` (AD-003) + `AccessAssignment` مرتبط + `AuditLogger` | Future-ready بالتصميم الكامل، **غير مبني** قبل Phase 2 |
| `IntegrationConnectionService` | واجهة مفهومية فقط (قسم M) | Future، **غير مبني** |

---

# G. Authorization — Central Authorization / Entitlement Resolution

**لا `if` مكررة بكل تطبيق.** مصدر واحد: `EntitlementResolver::resolve(User $user, MarketplaceItem $item, ?Organization $activeContext): AccessDecision`.

### `AccessDecision` (Value Object، ليس Model)
```
allowed: bool
reason: enum { has_access, needs_access, needs_subscription, needs_org_membership, item_unavailable }
```
**`reason` هو ما يحدد نص CTA مباشرة** (يطابق حرفيًا جدول قسم M بـ`marketplace-high-fidelity-full.md`: `needs_access` → "طلب الوصول"، `needs_subscription` مع `billing_model=organization_only` → "طلب من مدير المؤسسة").

### ترتيب الحل (Resolution Order)
```
1. item.status ∈ {published, updated}? لا → reason=item_unavailable, allowed=false (توقف فوري)
2. يوجد AccessAssignment(user=$user, subscription.marketplace_item=$item, status=active)
   مباشر (subscriber=user) أو عبر Seat ضمن اشتراك مؤسسة = $activeContext؟
   نعم → allowed=true, reason=has_access
3. يوجد Subscription(subscriber=user, item=$item, status=active) بلا AccessAssignment فعّال؟
   نعم → allowed=false, reason=needs_access
4. billing_model يسمح باشتراك شخصي (user_only|both)؟
   نعم → allowed=false, reason=needs_subscription (CTA: اشترك/ابدأ الآن)
5. billing_model=organization_only ولا اشتراك مؤسسي لـ$activeContext؟
   → allowed=false, reason=needs_subscription (CTA: طلب من مدير المؤسسة)
```
**المدخلات:** `$user`, `$item`, `$activeContext` (نتيجة CD-001 — بانتظاره، Phase 1 يمرّر `null` دائمًا = "شخصي" فقط، السلسلة كاملة تعمل بلا كسر، فقط الخطوة ٥ غير قابلة للتفعيل الفعلي قبل Phase 2).

### Gate رفيع (للتكامل الإطاري: Blade `@can`, Filament Policies, Middleware)
```
Gate::define('access-marketplace-item', fn($user, $item) =>
    EntitlementResolver::resolve($user, $item, ActiveOrganizationContext::current())->allowed);
```
هذا أول Gate/Policy حقيقي بالمشروع كاملًا (لا يوجد أي واحد اليوم — قسم A).

### حدود صارمة: Entitlement ≠ Authorization (AD-005)
`EntitlementResolver` و`Gate::define('access-marketplace-item', ...)` أعلاه **يجاوبان سؤالًا واحدًا فقط: "مؤهَّل يدخل التطبيق؟"** — لا أكثر. السلسلة الكاملة الرسمية للنظام:

```
Authentication → Organization Context → Entitlement → Application Access → Authorization → Action
     (Breeze)      (ActiveOrgContext)   (EntitlementResolver)   (فتح التطبيق)   (كل تطبيق بمفرده)   (فعل داخلي محدَّد)
```

**ما بعد "Application Access" خارج نطاق `EntitlementResolver` تمامًا وخارج نطاق هذي الوثيقة بالكامل** — "يقدر يشوف قضية X؟"، "يقدر يعدّل ملف Y؟"، "يقدر يحذف عميل Z؟" أسئلة **Authorization داخلية لكل تطبيق** (إفلاس تك مثلًا يبني نظام صلاحياته الخاص فوق بيانات الأدوار من Core Platform — `MembershipRole` الموجود فعليًا اليوم نقطة انطلاق منطقية لذلك، لكن **بناء ذاك النظام مسؤولية فريق التطبيق نفسه، لا Marketplace**).

**قاعدة صارمة يجب الالتزام بها بأي كود مستقبلي:** ممنوع معماريًا أي كود يستخدم خرج `EntitlementResolver`/`AccessDecision` مباشرة لاتخاذ قرار Authorization داخل تطبيق (مثال ممنوع: `if (Gate::allows('access-marketplace-item', $item)) { $case->delete(); }` — هذا خلط طبقتين، يجب يمر بـPolicy داخلية للتطبيق نفسها، ولو استهلكت بيانات دور المستخدم من نفس مصدر Core).

---

# H. Tenant Isolation

| الجدول | النطاق | القاعدة |
|---|---|---|
| `marketplace_items`, `partners`, `marketplace_categories`, `subscription_plans`, `plan_entitlements` | **Global** | لا تصفية مؤسسة — كتالوج عام |
| `subscriptions` | User-scoped أو Organization-scoped (حسب `subscriber_type`) | استعلام مؤسسي **يجب** يمرّر `organization_id = ActiveOrganizationContext` دائمًا — لا "كل اشتراكات المستخدم عبر كل مؤسساته" بنفس الاستعلام |
| `access_assignments` | User-scoped مباشرة، Organization-scoped ضمنيًا عبر `subscription_id` | نفس القاعدة أعلاه |
| `subscription_seats` | Organization-scoped | لا استعلام بلا `organization_id` صريح |
| `organizations`, `memberships` (Core، غير مُعدَّلة) | Global/Organization-scoped على التوالي | بلا تغيير |

**Active Organization Context — التنفيذ (Marketplace-side فقط، الجزء الآخر CD-001):** خدمة `App\Support\ActiveOrganizationContext::current(): ?Organization` تقرأ من الجلسة (`session('active_organization_id')`). **كتابة القيمة بالجلسة (المبدّل نفسه بالهيدر) خارج نطاق Marketplace — CD-001.** كل استعلام Organization-scoped بطبقة Repository/Service يستدعي هذي الخدمة، لا يقرأ الجلسة مباشرة (نقطة تجميع واحدة، قابلة للاختبار بمعزل).

**قاعدة صارمة:** لا `Global Scope` وحدها كخط دفاع (يمكن تجاوزها بـ`withoutGlobalScope` بالخطأ) — كل استعلام Organization-scoped بطبقة الـRepository يمرّر `organization_id` **صراحة** بالـWHERE، دفاعًا مزدوجًا.

---

# I. Subscription — Implementation كامل

| الانتقال | الفاعل المسموح | الشرط | الأثر الجانبي |
|---|---|---|---|
| **Create** | النظام (تلقائي، مجاني) أو المستخدم (مستقبلًا، مدفوع) | `billing_model` يسمح | ينشئ `Subscription(status=active)` + `AccessAssignment(status=active)` معًا بمعاملة واحدة (لعنصر مجاني شخصي) |
| **Activate** | — | — | **لا خطوة منفصلة لعنصر مجاني** — Create وActivate نفس اللحظة (لا Billing Engine، مطابق حرفيًا لمتطلب "الاشتراك المجاني يعمل بدون Billing") |
| **Suspend** | Admin (مستقبلًا) | — | `Subscription.status` يبقى `active`، `AccessAssignment.status=suspended` — **لا يمس السجل التجاري** (تفعيل مباشر لتنويه "Subscription≠Access") |
| **Cancel** | المستخدم/Admin | — | `Subscription.status=cancelled` + `AccessAssignment.status=revoked` معًا |
| **Expire** | النظام (مجدول، مستقبلًا) | نهاية `billing_cycle` | 🔒 Future — **لا معنى له لعنصر مجاني** (بلا دورة فوترة)، يُفعَّل فقط لحظة أول خطة مدفوعة حقيقية |
| **Renew** | النظام/المستخدم (مستقبلًا) | — | 🔒 Future، نفس السبب أعلاه |

**قاعدة Free بلا Billing Engine (مؤكَّدة تنفيذيًا):** كل الحقول المتعلقة بالفوترة (`price`, `billing_cycle` بـ`subscription_plans`) تبقى `null` لكل خطة Free — لا قيمة `0` مُختلَقة (فرق بين "لا سعر لأنه مجاني بلا مفهوم فوترة" و"سعره صفر" — الأول أصدق معماريًا).

---

# J. Access

قرار الوصول **محسوب دائمًا (Computed)، لا يُخزَّن كحالة منفصلة بمعزل عن `AccessAssignment.status`** — أي شاشة (My Apps، Application Details) تستدعي `EntitlementResolver` مباشرة أو عبر Gate، لا تقرأ حقلًا جاهزًا "isAccessible". هذا يمنع تعارض حالة بين شاشتين (قاعدة Cross-Screen Consistency، `high-fidelity-full.md` قسم ٥).

---

# K. Marketplace Catalog — Compatibility Layer

مطابق حرفيًا لـBlueprint قسم D، تفاصيل تنفيذية إضافية:

**Parity Validation:** Artisan Command جديد (`marketplace:catalog-parity-check`، **لا يُكتَب بهذي المرحلة، فقط مُحدَّد هنا**) يقارن `StaticPlatformAppsRepository::all()` مقابل `DatabaseMarketplaceRepository::all()` حقلًا حقلًا لكل عنصر — يفشل (Exit code ≠0) عند أي فرق. يُدمَج كخطوة CI قبل أي Cutover.

**Cutover:** سطر واحد بـ`MarketplaceServiceProvider` (قسم E). **Rollback:** نفس السطر بالعكس — فوري، بلا Migration عكسية.

**تفكيك تدريجي لـ`PlatformApps.php`:** لا يُحذَف بنفس Phase — يبقى موجودًا (Deprecated Comment فقط) لحد فترة تشغيل مستقرة بعد Cutover (Blueprint قسم D، خطوة ٤).

---

# L. Applications

**Shared Base:** `MarketplaceItem` (هوية، حالة، تسعير). **Type-specific Configuration:** `application_details.entry_route`. **Type-specific Behavior:** فعل "فتح" (Open) يحل `entry_route` عبر `route($entryRoute)` بدل `href` نصي مباشر بالمصفوفة الحالية — يمنع رابط مكسور لو تغيّر مسار Route لاحقًا.

**Lifecycle (Application):** يرث Lifecycle العام لـ`MarketplaceItem` (قسم U) — لا Lifecycle منفصل خاص بالتطبيقات، الفروق فقط بالـMessaging/CTA (مطابق لجدول M بـ`high-fidelity-full.md`، لا يُكرَّر هنا).

---

# M. Integrations

بنية Blueprint قسم ٦ (`Core → Shared APIs → Integrations`) — **لا Integration حقيقي يُبنى بهذي المرحلة.** الشكل المُحدَّد لحظة الحاجة الفعلية (Phase 3):
- `IntegrationMapperInterface` (عقد PHP، لا تطبيق) — تحويل بيانات مزوّد خارجي لصيغة داخلية.
- Endpoint موحَّد مستقبلي: `POST /integrations/{key}/webhook` → Job غير متزامن (`ProcessIntegrationWebhookJob`، غير مبني).
- `integration_connections`/`integration_events` — الشكل موثَّق بقسم C أعلاه (Future-ready)، لا Migration.

---

# N. Services

**إضافة نمذجة جديدة، غير مسمّاة صراحة بـBlueprint (توضيح، لا تعارض):** Service Lifecycle UX المعتمدة (`Discover→...→Completed/Cancelled`) تفترض سجلًا لكل طلب خدمة. Blueprint عرّف `Service` كنوع `MarketplaceItem` لكن لم يسمِّ كيان "الطلب" نفسه. هنا نضيفه بشكل يتسق مع النمط العام (Class Table Inheritance):

`service_requests` (Future-ready but Not Built): `id`, `marketplace_item_id`(FK), `requester_id`(FK users), `organization_id`(FK,nullable), `status`(string)، `requested_at`, `notes`(text,nullable), timestamps.

**لا Migration تُكتَب — الشكل موثَّق فقط لحظة أول Service حقيقي.**

---

# O. Partners

`Partner hasMany MarketplaceItem` (١:متعدد باتجاه واحد، Blueprint قسم ١). **Phase 1:** سجل واحد فقط (`partner_type=first_party`، اسم "حكم ورقم"). **لا افتراض "شريك = منتج واحد"** — العلاقة تدعم تعدد الأنواع من اليوم الأول بنيويًا، حتى لو غير مُستغَلة فعليًا الآن.

---

# P. AI Extension Points — حدود مفاهيمية فقط

**لا بنية تحتية AI تُبنى.** أسماء الواجهات المحجوزة (PHP Interfaces بالاسم فقط، بلا تطبيق):
```
AIProviderInterface        — تجريد فوق مزوّد النموذج
ModelSelectionPolicy       — أي نموذج لأي طلب
PromptVersion (مفهوم)      — تتبّع إصدار القوالب
AIContextProvider          — permission + tenant aware (يستهلك EntitlementResolver وActiveOrganizationContext مباشرة لحظة البناء)
AIUsageLog / AIAuditLog    — شكل الجدول فقط (Blueprint قسم ٧)، لا Migration
```
**نقطة الربط الوحيدة الفعلية الآن:** لا شيء — هذي أسماء محجوزة بالتوثيق فقط، لا Namespace/Interface فعلي بالكود قبل Phase 4.

---

# Q. Routes

| المسار | Method | Auth | Authorization | سياق مؤسسة؟ | Controller | الحالة |
|---|---|---|---|---|---|---|
| `/marketplace` | GET | لا | — | لا (Global) | `MarketplaceController@index` | ✅ موجود، يبقى (يتحول لقراءة عبر Repository) |
| `/marketplace/{key}` | GET | لا | — | لا | `MarketplaceController@show` | ✅ موجود، يبقى (مسار واحد لكل الأنواع — `key` فريد Global، لا حاجة لمسارات مفصولة بالنوع) |
| `/marketplace/interest` | POST | لا | — | لا | `ServiceInterestController@store` | ✅ موجود، بلا تغيير |
| `/my/apps` | GET | نعم | — | نعم (عرض) | جديد | 🔒 Phase 1 (يستبدل تدريجيًا قسم Dashboard الحالي — CD-002) |
| `/my/subscriptions` | GET | نعم | — | نعم (عرض) | جديد | 🔒 Phase 1 |
| `/my/integrations`, `/my/services` | GET | نعم | — | نعم | جديد | 🚫 لا رابط تنقّل فعلي قبل أول عنصر حقيقي (مطابق لقاعدة IA) |
| `/organization/{org}/apps` | GET | نعم | Owner/Admin فقط | نعم (إلزامي) | جديد | 🔒 Phase 2 |
| `/organization/{org}/access` | GET/POST | نعم | Owner/Admin فقط | نعم | جديد | 🔒 Phase 2 |
| `/organization/{org}/seats` | GET/POST/DELETE | نعم | Owner/Admin فقط | نعم | جديد | 🔒 Phase 2 |
| `/integrations/{key}/webhook` | POST | لا (توقيع بديل) | Webhook signature | — | جديد | 🚫 Phase 3 |

**لا Middleware مخصَّص جديد بـPhase 1** (كل شيء ضمن `auth` القياسي + Gate بالـController) — Middleware مخصَّص لـActive Organization Context هو تحديدًا CD-001.

---

# R. UI Mapping

للشاشات الست المعتمدة بـPilot + الشاشات الجديدة الأساسية بـFull — تفصيل الحالات/الفراغ/الخطأ **محدَّد مسبقًا بالوثيقتين، لا يتكرر هنا**، فقط الربط التقني:

| الشاشة | Route | Controller/Livewire | مصدر البيانات |
|---|---|---|---|
| Marketplace Home | `platform.home` | `PlatformController` (يُوسَّع) | `MarketplaceCatalogRepository` + `EntitlementResolver` لكل عنصر معروض |
| Catalog | `platform.marketplace` | `MarketplaceController@index` (موجود) | نفسه، + فلاتر Query String كما هي اليوم |
| Application Details | `platform.marketplace.show` | `MarketplaceController@show` (موجود) | نفسه + `AccessDecision` لتحديد CTA |
| Integration/Service Details | 🚫 | — | لا Route فعلي قبل أول عنصر حقيقي من كل نوع |
| My Apps | `/my/apps` (جديد) | Controller جديد | `Subscription`+`AccessAssignment` للمستخدم + `MarketplaceItem` المطابقة لـ`compatibility` |
| My Subscriptions | `/my/subscriptions` (جديد) | Controller جديد | `Subscription::with('plan','item')` للمستخدم |
| Organization/Access/Seat Management | 🔒 Phase 2 | — | — |

**لا Livewire مطلوب لأي شاشة Marketplace بـPhase 1** — كل التفاعل الحالي (فلاتر، بحث) Query String + إعادة تحميل Controller قياسي، مطابق تمامًا للنمط الحالي فعليًا بالكود (لا داعٍ لإدخال Livewire هنا بلا سبب، الفلاتر البسيطة لا تبرره).

---

# S. Filament/Admin

### MVP Admin (يُبنى Phase 1)
`MarketplaceItemResource` (نموذج بـFilament Tabs: عام + تبويب "تطبيق" يظهر فقط لو `type=application`) · `PartnerResource` (بسيط) · `MarketplaceCategoryResource` (بسيط).

### Future Admin (لا يُبنى الآن)
`SubscriptionPlanResource`/`PlanEntitlementResource` كواجهة إدارية مستقلة (بـPhase 1: تُدار عبر Seeder فقط، خطة Free وحيدة لكل عنصر — لا قيمة لواجهة إدارة كاملة لخطة واحدة ثابتة) · `IntegrationConnectionResource` (لا بيانات) · `AccessAssignmentResource`/`SubscriptionSeatResource` مستقلة (تُدار عبر شاشات Organization Management الخاصة بالمستخدم النهائي، لا Filament — قرار قصدي: هذي أدوات لمدير المكتب، لا لموظف حكم ورقم الإداري) · **عارض/واجهة لـ`audit_logs`: لا تُبنى إطلاقًا بـPhase 1 — قرار نهائي (AD-001)، ليس تأجيلًا معلَّقًا. الجدول يُقرأ يدويًا فقط (SQL/Tinker) وقت الحاجة الفعلية.**

### `AppSubscriptionResource` الموجود
يبقى كما هو (يقرأ `PlatformApps`) طوال فترة التعايش — **لا تعديل عليه قبل Cutover الفعلي**، عندها يُعاد توجيهه لـ`Subscription` الجديد أو يُستبدَل بـ`SubscriptionResource` جديد (قرار يُتَّخذ وقتها، ليس الآن).

---

# T. Business Rules

| # | القاعدة |
|---|---|
| BR-001 | إذا كان العنصر مجانيًا (`pricing_model=free`) ومنشورًا (`status=published`)، يبدأ المستخدم الاستخدام فورًا عبر `AccessAssignment` تلقائي — بلا خطوة دفع أو موافقة وسيطة. |
| BR-002 | اشتراك مؤسسة لا يمنح تلقائيًا وصولًا لكل الأعضاء إذا كانت الخطة تتطلب مقاعد (`seat_limit` غير null) — الوصول يمر عبر `SubscriptionSeat` صريح دائمًا بهذي الحالة. |
| BR-003 | مستخدم عضو بـOrganization A لا يقدر يصل لبيانات Organization B عبر سياق Organization A، حتى لو له عضوية فعّالة بكلتا المؤسستين — الوصول يُفلتَر دومًا بـ`ActiveOrganizationContext` الحالي فقط. |
| BR-004 | `Subscription.status=active` لا يعني تلقائيًا `AccessAssignment.status=active` — كل فحص وصول يمر عبر `EntitlementResolver`، لا عبر حالة الاشتراك مباشرة. |
| BR-005 | عنصر بحالة `suspended` يختفي من الكتالوج العام لكل مستخدم جديد، لكن يبقى ظاهرًا (بإشعار) للمشتركين الحاليين فقط. |
| BR-006 | عنصر `billing_model=organization_only` لا يظهر له زر اشتراك مباشر لمستخدم بلا عضوية مؤسسة فعّالة بسياقه الحالي — فقط "طلب من مدير المؤسسة". |
| BR-007 | حذف/إلغاء `Membership` لا يحذف `Subscription` القائم تلقائيًا — يُطلِق تنظيف `AccessAssignment`/`SubscriptionSeat` المرتبطة بذاك المستخدم بتلك المؤسسة فقط (Phase 2). |
| BR-008 | `Subscription.subscriber_type` يقبل فقط `user` أو `organization` — أي قيمة أخرى تُرفَض على مستوى قاعدة البيانات (CHECK) لا التطبيق فقط. |
| BR-009 | لا يمكن إنشاء `SubscriptionSeat` يتجاوز `SubscriptionPlan.seat_limit` لنفس الاشتراك — يُفحَص داخل معاملة (Transaction) بقفل صف (`lockForUpdate`) على `Subscription` وقت التعيين، مع تحقق من جهة الخادم حصرًا (AD-003). |
| BR-010 | عنصر `pricing_model=free` لا يملك أبدًا قيمة `price` غير null بأي `SubscriptionPlan` مرتبط به — القيمة `null` إلزامية، لا `0`. |
| BR-011 | `MarketplaceCatalogRepository::find()` يرجّع نفس Shape البيانات بغض النظر عن التطبيق الفعّال (Static/Database) — أي فرق بينهما يفشل Parity Check تلقائيًا قبل أي Cutover. |
| BR-012 | زائر غير مسجّل لا يرى أبدًا أي CTA غير "سجّل ودخل مجانًا"/"عرض التفاصيل" — لا "طلب الوصول"/"طلب من مدير المؤسسة" (هذي حالات تفترض هوية وعضوية معروفتين). |
| BR-013 (AD-002) | أي إنشاء/تعديل لسجل `Subscription` **يجب** يمر عبر `SubscriptionService` — استدعاء `Subscription::create()`/`::update()` مباشرة من أي طبقة أخرى بالكود يُعتبَر مخالفة معمارية تُرفَض بمراجعة الكود (Code Review)، لا مجرد أسلوب مفضَّل. |
| BR-014 (AD-001) | سجلات `audit_logs` **لا تُعدَّل ولا تُحذَف أبدًا** بعد الكتابة — أي محاولة استدعاء `update()`/`delete()` على `AuditLog` Model ترمي استثناءً فورًا (إنفاذ على مستوى الكود، لا اتفاق إجرائي فقط). |
| BR-015 (AD-002) | تحديد "من صاحب الاشتراك" (`subscriber_type`/`subscriber_id`) لا يُستعلَم مباشرة بأي Controller/View — يمر حصرًا عبر Repository/Resolver موحّد (نفس مصدر `EntitlementResolver`)، لتفادي استعلامات متكررة غير متسقة. |
| BR-016 (AD-005) | خرج `EntitlementResolver`/`AccessDecision` لا يُستخدَم أبدًا لاتخاذ قرار Authorization داخل أي تطبيق (مثال ممنوع: استخدام `allowed=true` كمبرر لصلاحية "تعديل"/"حذف" داخل التطبيق) — كل تطبيق يبني طبقة Authorization خاصة به بمعزل تام. |

---

# U. State Machines

### `MarketplaceItem` (Lifecycle، Blueprint قسم ٩)
```
draft → submitted → under_review → approved → published ⇄ updated
published/updated → suspended → published (استئناف) أو → deprecated (نهائي)
```
| الانتقال | الفاعل | الشرط | الأثر الجانبي |
|---|---|---|---|
| draft→published (مباشر) | Admin (First-party فقط) | — | يظهر بالكتالوج فورًا |
| draft→submitted | Partner (مستقبلًا) | — | 🔒 Phase 5 |
| published→suspended | Admin | — | يختفي من الكتالوج العام، يبقى للمشتركين (BR-005) |
| published/updated→deprecated | Admin | إشعار مسبق (🔒 يتطلب Notifications) | يبقى شغّالًا لحد تاريخ محدَّد |

### `Subscription` / `AccessAssignment` / `SubscriptionSeat`
مفصَّلة كاملة بقسم I أعلاه — لا تكرار.

### Integration / Service Request
🔒 Future بالكامل — الحالات موثَّقة بـ`high-fidelity-full.md` أقسام N/O، لا Migration/State فعلي قبل أول عنصر حقيقي من كل نوع.

**ملاحظة مهمة:** لا "Application State" (Available/Active/Needs Access...) كحقل DB منفصل — هذي حالة **محسوبة** من `MarketplaceItem.status` + خرج `EntitlementResolver` معًا وقت العرض، لا State Machine مستقلة لها (قسم J).

---

# V. Events

**قاعدة الاختيار:** حدث يُعرَّف فقط لو له مستهلك (Listener) حقيقي فعليًا بنفس المرحلة — لا بنية Event-driven استباقية.

**الأحداث الثمانية المعتمدة رسميًا (AD-001) — كل واحد له مستهلك حقيقي من اليوم الأول: `AuditLogger`:**

| الحدث | يُطلَق من | يُفعَّل فعليًا |
|---|---|---|
| `SubscriptionCreated` | `SubscriptionService::create()` | **Phase 1** |
| `SubscriptionActivated` | `SubscriptionService::activate()` | **Phase 1** (للعنصر المجاني: نفس لحظة `Created`، Blueprint قسم I) |
| `SubscriptionCancelled` | `SubscriptionService::cancel()` | **Phase 1** |
| `SubscriptionSuspended` | `SubscriptionService::suspend()` | **Phase 1** |
| `AccessGranted` | `SubscriptionService::create()` (شخصي) أو `SeatAssignmentService::assign()` (مؤسسي) | **Phase 1** (شخصي) / Phase 2 (مؤسسي) |
| `AccessRevoked` | نفسه | **Phase 1** (شخصي) / Phase 2 (مؤسسي) |
| `SeatAssigned` | `SeatAssignmentService::assign()` | Phase 2 فقط (لا معنى قبل وجود مقاعد فعلية) |
| `SeatReleased` | `SeatAssignmentService::release()` | Phase 2 فقط |

**كل حدث أعلاه يستدعي `AuditLogger` مباشرة ضمن نفس المعاملة (Transaction) — لا Queue/Listener غير متزامن لهذي الثمانية تحديدًا** (الكتابة يجب تنجح أو تفشل مع العملية نفسها، لا "لاحقًا" — سجل Audit مفقود بسبب فشل Queue غير مقبول لأحداث لا يمكن استرجاعها). **لا حدث تاسع يُضاف بلا قرار AD جديد** (نفس قيد "لا نوع Subscriber ثالث" بـAD-002 يُطبَّق هنا بنفس الروح — قائمة مغلقة، لا توسّع تدريجي بلا مراجعة).

| الحدث | المستهلك | الحالة |
|---|---|---|
| `MembershipRevoked` | تنظيف `SubscriptionSeat`/`AccessAssignment` (BR-007) | Phase 2 فقط — لا مستهلك قبل وجود Seats فعليًا |
| `IntegrationConnected`, `ServiceRequestSubmitted` | — | 🚫 لا يُعرَّف قبل أول مستهلك حقيقي (Phase 3+) |

---

# W. Notifications

**لا Notification تُبنى بـPhase 1.** أول قيمة فعلية حقيقية لإشعار: "طلب الوصول" (BR-006) يحتاج تنبيه مدير المؤسسة — هذا يتطلب Phase 2 (اشتراكات مؤسسية) ليكون له معنى أصلًا. حتى ذاك الحين: تأكيدات داخل الجلسة (Toast، موثَّق بـDesign System قسم ٣١) كافية تمامًا — لا خدمة Notifications خلفية مطلوبة لـPhase 1.

---

# X. Audit

**معتمد رسميًا (AD-001) — طبقة Audit Minimal، لا منصّة تدقيق.** يُسجَّل: تغييرات Subscription (إنشاء/تفعيل/إلغاء/تعليق)، تغييرات Access (منح/سحب)، تعيين/إزالة Seat — الثمانية أحداث المحدَّدة بقسم V بالاسم، لا أكثر. كل سجل يحمل `organization_id` (لو ينطبق) لضمان Tenant Isolation حتى بالتدقيق نفسه (لا استعلام Audit عابر للمؤسسات إلا بصلاحية منصّة عليا مستقبلية، خارج نطاق هذي المرحلة).

**ما لا يُبنى (تأكيد صريح، AD-001):** لا UI لعرض السجلات، لا بحث متقدم، لا Analytics، لا Export، لا Audit Dashboard — الجدول append-only يُكتَب إليه فقط، يُقرأ يدويًا فقط وقت حاجة تشغيلية فعلية (نزاع/تدقيق مطلوب فعليًا). أي واجهة استعلام مستقبلية قرار منفصل يتطلب مراجعة جديدة، لا امتداد تلقائي لهذي الطبقة.

**الفرق الجوهري عن باقي "الفجوات المؤجَّلة" بالمشروع (Notifications، Reviews، إلخ):** تلك تُبنى JIT بأمان لأن غيابها اليوم لا يفقد بيانات لا يمكن استرجاعها. غياب Audit للأحداث الثمانية تحديدًا **يفقد بيانات لا يمكن إعادة بنائها لاحقًا بأي شكل** — هذا الفرق بالذات هو ما بَرَّر الاستثناء (AD-001)، لا معيار عام "منصة قانونية تحتاج كل شيء من اليوم الأول".

---

# Y. Security

| البُعد | القرار |
|---|---|
| Authorization | `EntitlementResolver` + Gate واحد (قسم G) — أول طبقة تفويض حقيقية بالمشروع كاملًا |
| Tenant Isolation | استعلام صريح دومًا (قسم H)، لا اعتماد كلي على Global Scope |
| Mass Assignment | كل Model جديد يستخدم `#[Fillable]` صراحة (نمط قائم)، لا `$guarded=[]` أبدًا |
| Sensitive Fields | `integration_connections.credentials` (مستقبلًا) عبر Laravel `encrypted` cast — لا تخزين نص صريح مهما كان السبب |
| API Security | لا API عام بـPhase 1 (لا API Gateway، Blueprint قسم ٨) |
| Webhook Security | توقيع (Signature Verification) إلزامي مفاهيميًا لحظة أول Webhook حقيقي — لا تطبيق الآن |
| Rate Limiting | `throttle` القياسي بـLaravel وقت الحاجة الفعلية (نموذج الاهتمام مثلًا) — لا بنية مخصَّصة |
| Audit | Audit Minimal فعّال من Phase 1 (AD-001، قسم X) — append-only، إنفاذ عدم التعديل على مستوى الـModel |
| Data Access | كل Repository جديد يمرّر السياق (User/Organization) صراحة كمعامل، لا يعتمد على "الحالة الحالية" الضمنية بأي مكان |

---

# Z. Testing

جدول اختبار لكل Business Rule — **الأولوية القصوى: Tenant Isolation (Organization A مقابل B)**، كما طُلب صراحة:

| BR | نوع الاختبار | السيناريو |
|---|---|---|
| BR-001 | Feature | مستخدم جديد يفتح بوابة معرفة → `AccessAssignment` يُنشأ تلقائيًا، بلا أي فعل يدوي |
| BR-002 | Feature + Authorization | اشتراك مؤسسي بخطة `seat_limit=5`، عضو بلا Seat → `EntitlementResolver` يرجّع `needs_access` لا `has_access` |
| BR-003 | **Tenant Isolation (الأهم)** | مستخدم عضو بـOrganization A وB معًا (مطابق تمامًا لمثال "محمد" بـBlueprint) — سياق نشط=A، استعلام بيانات تطبيق تشغيلي (مثال مستقبلي: قضية) لا يرجّع أي صف يخص B، حتى بصلاحية تقنية كاملة بـB |
| BR-004 | Unit | `Subscription.status=active` + `AccessAssignment.status=suspended` → `EntitlementResolver` يرجّع `allowed=false` |
| BR-005 | Feature | عنصر `suspended` لا يظهر بـ`MarketplaceController@index` لمستخدم غير مشترك، **يظهر** لمشترك فعلي |
| BR-006 | UI/Authorization | عنصر `organization_only`، مستخدم بلا عضوية → لا زر اشتراك مباشر بصفحة التفاصيل إطلاقًا (DOM لا يحتويه، لا Disabled فقط) |
| BR-007 | Feature (Phase 2) | حذف Membership → Seat/AccessAssignment المرتبطة تُبطَل تلقائيًا |
| BR-008 | Unit/DB | محاولة إدراج `subscriber_type='team'` (قيمة غير صالحة) → استثناء قاعدة بيانات |
| BR-009 | **Concurrency صريح (Phase 2)** | راجع تفصيل السيناريو والتحدي التقني أسفل الجدول — ليس Unit Test عادي |
| BR-010 | Unit | `SubscriptionPlan` بعنصر مجاني → `price` دائمًا `null`، لا `0` |
| BR-011 | **Parity (الأهم لـCutover)** | مقارنة آلية `StaticPlatformAppsRepository::all()` مقابل `DatabaseMarketplaceRepository::all()` — فشل عند أي فرق حقل واحد |
| BR-012 | Authorization | زائر غير مسجّل بصفحة تفاصيل عنصر Organization-only → لا نص "طلب الوصول" ظاهر إطلاقًا |
| BR-013 | Static/Process | فحص Code Review آلي أو يدوي: لا استدعاء `Subscription::create()` خارج `SubscriptionService` بكامل الكود (بحث نصي بسيط كافٍ كخط دفاع أول) |
| BR-014 | Unit | استدعاء `AuditLog::find($id)->update([...])` أو `->delete()` → استثناء فورًا |
| BR-015 | Unit | تحديد صاحب اشتراك عبر Resolver موحّد يرجّع نفس النتيجة سواء `subscriber_type=user` أو `organization` — لا مسار كود منفصل لكل حالة |
| BR-016 | Unit | محاولة استخدام `AccessDecision::$allowed` مباشرة كمدخل لقرار Authorization داخل اختبار وهمي لتطبيق → يفشل الاختبار عمدًا (يوثّق القاعدة كاختبار سلبي Regression) |

### AD-003 — سيناريو اختبار Concurrency الصريح لـBR-009
**السيناريو المطلوب حرفيًا:** مؤسسة بخطة `seat_limit=5`، ٤ مقاعد مستخدَمة فعليًا. طلبان متزامنان (User A وUser B) يحاولان أخذ آخر مقعد (المقعد الخامس) **بنفس اللحظة تقريبًا**. النتيجة المطلوبة: واحد فقط ينجح (5/5)، الثاني يُرفَض بوضوح (لا 6/5 أبدًا).
**التحدي التقني الحقيقي (يُسجَّل هنا بصراحة، لا يُخفى):** اختبارات Laravel القياسية (`RefreshDatabase`/معاملة اختبار واحدة مُغلَّفة) تُنفَّذ بعملية واحدة تسلسلية — **لا تُنتِج تزامنًا حقيقيًا بشكل افتراضي**. تنفيذ اختبار Concurrency حقيقي يتطلب إما: (أ) عمليتان/اتصالا قاعدة بيانات منفصلان فعليًا (خارج غلاف معاملة الاختبار الواحدة، عبر `pcntl_fork` أو عمليتا Artisan منفصلتان) — الأدق لكن الأعقد تقنيًا. (ب) اختبار حتمي (Deterministic) يحاكي الشرط عبر حجز الصف يدويًا بمعاملة مفتوحة ثم محاولة إدراج ثانٍ بمعاملة مستقلة قبل إغلاق الأولى، والتأكد من انتظار/رفض الثانية بحسب سلوك `lockForUpdate` الفعلي. **التوصية:** الخيار (ب) — أبسط تنفيذيًا وكافٍ لإثبات صحة القفل دون تعقيد بنية اختبار متعددة العمليات، **قرار تنفيذي يُتَّخذ وقت كتابة الاختبار الفعلي، لا هنا.**

**أنواع الاختبار المطلوبة إجمالًا:** Unit (Models/EntitlementResolver المعزول) · Feature (Controllers/Routes) · Authorization (Gate) · **Tenant Isolation (فئة مستقلة، لا تُدمَج ضمن Feature عامة — أولوية قصوى صريحة)** · **Concurrency (فئة مستقلة جديدة، AD-003 — BR-009 تحديدًا)** · UI/Livewire (لا شيء بـPhase 1، لا Livewire مستخدَم) · Integration Tests (🚫 لا قيمة قبل أول Integration حقيقي).

---

# AA. Migration Strategy (خطة فقط، بدون تنفيذ)

| Phase | المحتوى |
|---|---|
| **Phase 0** | توثيق/Baseline — هذي الوثيقة نفسها + اعتمادها |
| **Phase 1** | Schema جديد **بالتوازي** مع النظام الحالي — كل جداول قسم C (Required Now) تُنشأ، `PlatformApps` يبقى الفعّال |
| **Phase 2** | Compatibility Layer — `MarketplaceCatalogRepository` + تطبيقاه يُبنيان، `DatabaseMarketplaceRepository` **غير مفعَّل بعد** |
| **Phase 3** | Data Parity — تعبئة `marketplace_items`/`partners`/`subscription_plans` من `PlatformApps::all()` حرفيًا + تشغيل Parity Check (BR-011) لحد تطابق ١٠٠٪ |
| **Phase 4** | Read Switch — تبديل الـBinding لـ`DatabaseMarketplaceRepository` (سطر واحد، قسم E) — القراءة فقط، لا كتابة بعد |
| **Phase 5** | Write Switch — أي كتابة جديدة (اشتراك جديد) تُنشئ `Subscription` بالجدول الجديد بدل `AppSubscription` القديم |
| **Phase 6** | Validation — فترة مراقبة إنتاجية (Zero regression)، لا حذف بعد |
| **Phase 7** | Old Source Deprecation — حذف `PlatformApps.php` و`app_subscriptions` (القديم) فقط بعد فترة استقرار مؤكَّدة، مرحلة منفصلة زمنيًا عن Phase 6 |

**لا حذف لأي مصدر قديم قبل إثبات Parity الكامل (BR-011) بكل الحالات — التزام حرفي بمتطلبك.**

---

# AB. Implementation Phases (حسب Dependency)

**قرار تسلسل صريح (2026-08-08):** لا يبدأ أي تنفيذ فعلي بـ"كل قسم C دفعة واحدة". أول Implementation Slice **أضيق بكثير** مما كان محدَّدًا سابقًا — كتالوج فقط، لا شيء آخر. الهدف: إثبات إن الانتقال من `PlatformApps` لكتالوج DB حقيقي **ممكن بلا كسر حكم ورقم القائمة**، قبل أي التزام ببناء طبقات أعمق (اشتراكات/وصول/مؤسسات). Phase 1 الأصلية بالمسودة السابقة **انقسمت الآن لشريحتين منفصلتين تمامًا (1a وb1)** — لا تُنفَّذان معًا، ولا تبدأ 1b قبل نجاح 1a واعتماده صراحة كخطوة منفصلة.

### Phase 1a — Marketplace Catalog Only (أول Slice، الأضيق والأهم)
**Goal:** الإثبات الوحيد المطلوب بهذي الشريحة: كتالوج DB-backed حقيقي، بلا أي مساس بمنطق الاشتراك/الوصول. **لا `Subscription`، لا `AccessAssignment`، لا `EntitlementResolver`، لا `audit_logs`، لا My Apps/My Subscriptions بهذي الشريحة تحديدًا** — كلها تنتمي لـPhase 1b التالية.
**Dependencies:** لا شيء (لا Core، لا CD أي منها).
**DB:** `marketplace_items`, `application_details`, `partners`, `marketplace_categories` **فقط** — لا `subscriptions`/`access_assignments`/`subscription_plans`/`plan_entitlements`/`subscription_seats`/`audit_logs` بهذي الشريحة (تُنشأ بـPhase 1b، لا الآن).
**Backend:** `MarketplaceCatalogRepository` + `StaticPlatformAppsRepository` + `DatabaseMarketplaceRepository` + Parity Check (BR-011).
**Frontend:** **لا تغيير على أي View** — `MarketplaceController`/`PlatformController` الحاليان يبقيان كما هما تمامًا بالواجهة الخارجية (نفس الـRoutes، نفس الـBlade)، فقط مصدر البيانات الداخلي يتغيّر خلف `MarketplaceCatalogRepository`.
**Admin:** `MarketplaceItemResource`, `PartnerResource`, `MarketplaceCategoryResource`.
**Tests:** BR-011 (Parity — المحك الحقيقي الوحيد لنجاح هذي الشريحة) فقط. لا اختبارات Subscription/Access هنا (لا كيانات لها بعد).
**Acceptance Criteria (المعيار الحقيقي الوحيد لنجاح هذي الشريحة، بصياغتك حرفيًا):**
> **المستخدم الحالي لا يشعر بأي تغيير سلبي في حكم ورقم، بينما أصبح Marketplace لأول مرة مدفوعًا بكتالوج حقيقي قابل للتوسع.**
عمليًا: Parity 100% بين المصدرين، صفر تغيير مرئي بأي شاشة، بوابة معرفة تعمل بلا أي كسر، Cutover (تبديل سطر Binding واحد) قابل للتنفيذ والتراجع الفوري.
**Risks:** Cutover يكسر رابطًا مباشرًا لو `key` لم يُطابَق حرفيًا — يُخفَّف بـParity Check الإلزامي قبل أي Cutover (قسم AA).

### Phase 1b — Personal Access (بعد نجاح 1a واعتماده صراحة، لا قبل)
**Goal:** وصول شخصي كامل (Subscription≠Access) — الآن فقط يُبنى فوق كتالوج مُثبَت الصحة من 1a.
**Dependencies:** **نجاح Phase 1a الكامل (Parity 100% + Cutover مستقر) — بوابة عبور إلزامية، لا استثناء.**
**DB:** `subscriptions`, `subscription_plans`, `plan_entitlements`, `access_assignments`, `subscription_seats` (بنية فقط)، `audit_logs`.
**Backend:** `SubscriptionService`, `EntitlementResolver`, `AuditLogger`, توسيع `FreeAppProvisioner` (AD-001/002/005 مطبَّقة بالكامل من هنا).
**Frontend:** My Apps، My Subscriptions (Routes جديدة) — أول شاشات جديدة فعليًا بالمشروع.
**Admin:** لا جديد (يستخدم موارد 1a).
**Tests:** BR-001 إلى BR-016 المنطبقة على وصول شخصي (لا يتطلب مؤسسة) — يستثني BR-002/007/009 (تتطلب Phase 2).
**Acceptance Criteria:** تفعيل تلقائي فوري لعنصر مجاني، CTA يعكس `AccessDecision` حقيقي، My Apps/My Subscriptions تعملان ببيانات حقيقية لا وهمية.
**Risks:** أقل بكثير من 1a (لا يمس شيئًا مرئيًا للمستخدم الحالي — كل ما هنا شاشات/كيانات جديدة بمعزل).

### Phase 2 — المؤسسات (Seats + Active Org Context)
**Goal:** اشتراك مؤسسي حقيقي أول مرة.
**Dependencies:** نجاح Phase 1b + **CD-001 (Active Organization Context Switcher، AD-004) — Core Dependency، يُنفَّذ خارج Marketplace مباشرة أولًا.**
**DB:** تفعيل استخدام `subscription_seats` فعليًا (الجدول موجود من Phase 1).
**Backend:** `SeatAssignmentService`, تفعيل خطوة ٥ بـ`EntitlementResolver` (قسم G).
**Frontend:** Organization/Access/Seat Management.
**Admin:** لا جديد (هذي شاشات مستخدم نهائي لا Filament).
**Tests:** BR-002, BR-007, BR-009 (Race Condition).
**Acceptance Criteria:** مدير مؤسسة يقدر يعيّن/يسحب مقعد، عضو بلا مقعد يرى "طلب الوصول" لا زر مباشر.
**Risks:** يعتمد كليًا على CD-001 — **لا يبدأ Phase 2 فعليًا قبل حل هذا الاعتماد**.

### Phase 3 — Integrations الأولى
**Goal:** أول تكامل حقيقي واحد.
**Dependencies:** Phase 1 + وجود شريك/مزوّد فعلي جاهز تقنيًا.
**DB:** `integration_details`, `integration_connections`, `integration_events`.
**Risks:** لا يبدأ بلا تكامل حقيقي مؤكَّد — **لا Migration استباقية** (مطابق حرفيًا لطلبك).

### Phase 4 — AI الأولى
**Goal:** أول ميزة AI حقيقية.
**Dependencies:** Phase 1-2 (Context/Access resolved أولًا — AI يعتمد عليهما).
**Risks:** لا يبدأ بلا حاجة منتجية فعلية مؤكَّدة.

### Phase 5 — Partner Ecosystem
**Goal:** أول شريك خارجي حقيقي.
**Dependencies:** Phase 1 + قرار عمل/قانوني خارج نطاق تقني (مطابق Blueprint قسم ١٠).

---

# AC. Definition of Done

**Marketplace Catalog Only (Phase 1a — أول Slice، بوابة العبور لأي شيء آخر):**
- [ ] DB-backed (`marketplace_items` + Repository)
- [ ] البحث يعمل (نص حر، مطابق للسلوك الحالي حرفيًا)
- [ ] الفلاتر تعمل (الكل/مجاني/قريبًا، مطابق للسلوك الحالي حرفيًا)
- [ ] Empty States تعمل (نتيجة بحث فارغة) — مطابق للسلوك الحالي
- [ ] التطبيقات الثمانية الحالية محفوظة حرفيًا (بوابة معرفة تعمل بلا أي كسر)
- [ ] Compatibility Layer فعّال (Static افتراضيًا، قابل للتبديل بسطر واحد)
- [ ] Parity Check يمر ١٠٠٪ (BR-011) — **بلا هذا البند، لا اعتماد للشريحة بأي حال**
- [ ] لا Regression على Core Platform (بوابة معرفة، Auth، الدليل القانوني)
- [ ] **لا `Subscription`/`AccessAssignment`/`EntitlementResolver`/`audit_logs` موجودة بهذي الشريحة إطلاقًا** — أي وجود لأي منها = خروج عن نطاق 1a المتفَق عليه
- [ ] **اختبار القبول النهائي (بصياغتك):** مستخدم حالي يستخدم حكم ورقم بلا أي شعور بتغيير — الفرق الوحيد الملموس: Marketplace أصبح مدفوعًا بكتالوج حقيقي قابل للتوسع لاحقًا

**Application Access Flow (Phase 1b — لا تبدأ قبل اعتماد 1a أعلاه صراحة):**
- [ ] `SubscriptionService` هو نقطة الدخول الوحيدة (لا `Subscription::create()` مباشر بأي مكان — BR-013)
- [ ] تفعيل تلقائي لعنصر مجاني (`Subscription`+`AccessAssignment` معًا، معاملة واحدة)
- [ ] CTA يعكس الحالة الحقيقية عبر `EntitlementResolver`/`AccessDecision` (has_access/needs_access/needs_subscription)
- [ ] My Apps/My Subscriptions تعرضان بيانات حقيقية فور التفعيل
- [ ] `audit_logs` يُكتَب فعليًا للأحداث الأربعة المنطبقة على وصول شخصي (Created/Activated/Cancelled/Suspended + AccessGranted/Revoked) — Append-only مُنفَذ على مستوى الـModel (BR-014)
- [ ] الاختبارات تمر (BR-001، BR-004، BR-006، BR-008، BR-010، BR-012 إلى BR-016 المنطبقة على وصول شخصي)

**Organization Access/Seat Management (Phase 2):** مؤجَّل بالكامل لحد حل CD-001 — لا Definition of Done له قبل ذلك.

---

# AD. Non-Goals (ما لن يُبنى الآن — قائمة موحَّدة)

Billing Engine الفعلي (بوابة دفع، فواتير، تجديد تلقائي) · معالجة دفع حقيقية · Partner Portal · تكاملات خارجية حقيقية · بنية AI كاملة (Extension Points فقط) · Reviews/Ratings · Advanced Analytics/Usage Tracking حقيقي · Recommendation AI (Rules-based فقط) · Notification Center دائم · عرض مدمج عبر مؤسسات متعددة (Multi-org merge) · Trial UX · Multi-Tenancy Filament · API Gateway عام · Webhook حقيقي · Audit Logs عامة (معلَّق على AC-003 تحديدًا، لا "ممنوع" مطلق).

---

# AE. Architecture Conflicts

**الحالة: الثلاثة محسومة رسميًا (2026-08-08) — راجع Architecture Decisions أعلى الوثيقة. النص الأصلي لكل تعارض محفوظ أدناه للتتبّع (Traceability)، مع سطر "القرار النهائي" أسفل كل واحد.**

### AC-001 — Polymorphic Subscriber: سلامة مرجعية (Referential Integrity) مقابل أدوات Filament
**القرار الحالي (Blueprint قسم ٢):** `Subscription.subscriber_type`/`subscriber_id` Polymorphic.
**المشكلة:** (أ) لا FK حقيقي على مستوى قاعدة البيانات لـ`subscriber_id` (لا يمكن ربطه بجدولين مختلفين بقيد FK تقليدي) — الاعتماد كليًا على تحقق تطبيقي. (ب) Filament v3.3 لا يدعم أصلًا حقل Morph Select بحث/تحميل مسبق دون مكوّن مخصَّص إضافي (جهد تنفيذي حقيقي، لا "استخدام جاهز").
**سبب التعارض:** منصة قانونية/مالية تفترض سلامة بيانات صارمة (نفس منطق Blueprint قسم ٧ لتبرير Audit الإلزامي بـAI) — غياب FK حقيقي هنا نقطة ضعف حقيقية، ولو نظريًا.
**الخيارات:** (أ) الإبقاء على Polymorphic كما هو، تخفيف الخطر عبر CHECK constraint على `subscriber_type` + تحقق تطبيقي صارم بطبقة الـModel، وبناء Filament Form Component مخصَّص. (ب) استبداله بعمودين نول-ابل (`user_id`, `organization_id`) + CHECK يفرض "واحد فقط معبّى" — استُبعِد أصلًا بـBlueprint لنفس السبب لكن بحجة مختلفة (قبل توفر CHECK constraints بأغلب محركات DB الحديثة كخيار عملي). (ج) جدولان منفصلان بالكامل (`user_subscriptions`/`organization_subscriptions`) — استُبعِد أصلًا بـBlueprint (تكرار منطق).
**التوصية:** الإبقاء على القرار المعتمد (أ) — الجهد الإضافي (CHECK constraint + Filament component مخصَّص) محدود وواحد-مرة، بينما مرونة عنصر مشترك واحد بمنطق فوترة واحد تستحق ذلك.
**القرار النهائي: ⚠️ موافقة مشروطة — راجع AD-002.** موافَق على الإبقاء على Polymorphic، لكن **ليس لمجرد أنه قرار Blueprint سابق** — مشروط بخمسة ضوابط إضافية إلزامية غير موجودة بالتوصية الأصلية: Enum مغلق بقيمتين فقط (لا نوع ثالث بلا قرار معماري جديد)، حظر معماري صريح لأي `Subscription::create()` خارج طبقة Domain/Service، Repository/Resolver موحّد لتحديد صاحب الاشتراك، اختبارات إلزامية موسّعة (Cross-organization access rejection ضمنها صراحة). التفاصيل التنفيذية الكاملة بقسم D/F/G/T أدناه.

### AC-002 — آلية إنفاذ حد المقاعد (`seat_limit`) غير محدَّدة بـBlueprint
**القرار الحالي:** `SubscriptionPlan.seat_limit` يحدّ عدد `SubscriptionSeat` — بلا تحديد آلية الإنفاذ.
**المشكلة:** تعيين مقعدين متزامنين على آخر مقعد متاح قد يتجاوز الحد بدون حماية صريحة (Race Condition).
**سبب التعارض:** Blueprint حدد البنية (الجداول) لا سلوك الإنفاذ التشغيلي — فجوة تحديد لا تعارض قرار قائم.
**الخيارات:** (أ) فحص تطبيقي بمعاملة (Transaction) + قفل صف (`lockForUpdate`) على `Subscription` وقت التعيين. (ب) عمود عدّاد منفصل (`seats_used`) بزيادة ذرّية. (ج) قبول المخاطرة (منخفضة الاحتمال عمليًا — فعل إداري يدوي نادر التزامن).
**التوصية:** (أ) — كافٍ لمستوى التزامن المتوقَّع (مدير واحد بالعادة يتصرف تسلسليًا)، بلا تعقيد هندسي إضافي (عمود عدّاد) غير مبرَّر بحجم الاستخدام الحالي.
**القرار النهائي: ✅ موافقة كاملة — راجع AD-003.** بشرط دقة أعلى من "استخدم lockForUpdate" وحدها: **Transaction + `lockForUpdate()` + تحقق من جهة الخادم حصرًا (لا اعتماد على الواجهة إطلاقًا) + قيود DB مساندة معًا**، لا أي عنصر منفردًا. **اختبار Concurrency صريح إلزامي** (لا Unit Test عادي يكفي) — تفصيل السيناريو والتحدي التقني لتنفيذه بقسم Z أدناه.

### AC-003 — نطاق Audit Logging: JIT (Blueprint قسم ٨) مقابل حاجة يوم-واحد لتغييرات Subscription/Access
**القرار الحالي:** Audit Logs "❌ غير موجود، تُبنى Just-In-Time" (Blueprint قسم ٨)، بلا استثناء مذكور.
**المشكلة:** هذي الوثيقة (قسم X) تحتاج تحديد ما يُسجَّل من تغييرات Subscription/Access "باحترام Tenant Isolation" — وهذا يفترض آلية تسجيل فعلية موجودة، لا مفهومًا مؤجَّلًا بالكامل.
**سبب التعارض:** Blueprint نفسه (قسم ٧) يبرر إلزامية تسجيل AI "من التصميم لا إضافة لاحقة" تحديدًا لأن المنصة قانونية/مالية — لكن هذا التبرير صيغ بسياق AI فقط، لم يُوسَّع صراحة لعموم تغييرات Subscription/Access. لا قرار صريح يحسم هل نفس المنطق ينطبق هنا.
**الخيارات:** (أ) الإبقاء على القرار الحرفي — لا `audit_logs` بـPhase 1 حتى لتغييرات Subscription/Access، تأجيل كامل لحد أول حاجة تشغيلية فعلية (نزاع/تدقيق طُلِب فعليًا). (ب) استثناء ضيّق: جدول `audit_logs` خفيف جدًا (Append-only، بلا واجهة إدارية) لأحداث Subscription/Access/Seat فقط، من Phase 1، بحجة إن بيانات تاريخية مفقودة لا يمكن "بناؤها لاحقًا بأثر رجعي" (خلاف ذلك مع Reviews/Ratings مثلًا القابلة للتأجيل بلا خسارة بيانات تاريخية). (ج) نفس (ب) لكن أوسع (كل الإجراءات الإدارية).
**التوصية:** (ب) — كلفة منخفضة جدًا (جدول واحد، لا منطق إضافي)، بينما كلفة غيابه لو احتيج لاحقًا غير قابلة للتعويض (لا JIT يستطيع استرجاع سجل تاريخي مفقود). **هذا تجاوز فعلي، ولو محدود، لنص Blueprint الصريح — يحتاج قرارك المباشر قبل أي تنفيذ.**
**القرار النهائي: ✅ موافقة كاملة — راجع AD-001.** الخيار (ب) معتمد حرفيًا، بثمانية أحداث محدَّدة بالاسم (لا "كل تغيير إداري" — تحديد أضيق حتى من الخيار (ب) الأصلي المقترَح). **هذا تعديل رسمي معتمد على نص Blueprint قسم ٨** — تم تحديثه فعليًا هناك (لم يعد تعارضًا قائمًا). الشكل التنفيذي الكامل بقسم C/V/X أدناه.

---

# AF. Core Dependencies

### CD-001 — Active Organization Context Switcher (Header عام)
**سبق تسجيله بـ`marketplace-product-ux-architecture.md` قسم U، يُعاد هنا كـDependency تنفيذي رسمي.**
**يحجب:** Phase 2 بالكامل (Organization/Access/Seat Management) — بلا هذا، لا معنى لـ"مؤسسة نشطة" بجلسة المستخدم (AD-004).
**لا يحجب:** Phase 1a ولا Phase 1b (اشتراكات شخصية تعمل بلا أي اعتماد على السياق المؤسسي، `EntitlementResolver` يعمل بـ`$activeContext=null` دائمًا).

### CD-002 — تطوير قسم "تطبيقاتي" بـ`dashboard.blade.php`/`DashboardController` الحالي
**سبق تسجيله بنفس المصدر.** **يحجب:** لا شيء تقنيًا — My Apps الجديدة (قسم R) تعمل كصفحة مستقلة بمعزل تمامًا. **الفائدة فقط:** تفادي ازدواجية منطق لاحقًا بين Dashboard وMy Apps — يمكن تأجيله لما بعد Phase 1b بلا أي مخاطرة.

### CD-003 — واجهة مستخدم لإدارة أعضاء المؤسسة (بدل Filament فقط)
**سبق تسجيله بنفس المصدر.** **يحجب جزئيًا:** Access/Seat Management (Phase 2) تعمل تقنيًا على أعضاء مُضافين مسبقًا عبر Filament — لكن تجربة "مدير يدعو عضوًا جديدًا بنفسه" غير مكتملة بدونه. **لا يحجب** البدء بـPhase 2 على أعضاء موجودين فعليًا.

---

**ملخص الحالة:** Implementation Specification كاملة (٣٢ قسمًا A-AF + Architecture Decisions). **الثلاثة تعارضات محسومة رسميًا (AD-001/002/003) + قرار خامس جديد (AD-005 — Entitlement≠Authorization).** لا تعارض معلَّق. أول Implementation Slice معتمد ومحدَّد بدقة: **Phase 1a — Marketplace Catalog Only**، بمعزل تام عن أي منطق اشتراك/وصول، معيار قبوله الوحيد: "المستخدم الحالي لا يشعر بأي تغيير سلبي، بينما أصبح Marketplace مدفوعًا بكتالوج حقيقي." لا Phase 1b تبدأ قبل اعتماد نجاح 1a صراحة. ثلاثة اعتماديات Core (CD-001/002/003) تبقى مسجَّلة، CD-001 يحجب Phase 2 حصرًا (لا 1a ولا 1b). **بانتظار الإذن الصريح لبدء كتابة الكود الفعلي لـPhase 1a تحديدًا — لم يُمنَح بعد.**
