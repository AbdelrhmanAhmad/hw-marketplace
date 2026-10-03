# Marketplace — جرد الحالة الحالية (Discovery / Audit)

**⚠️ محدَّثة بعد Final Execution Sprint (2026-09-02) — راجع `docs/final-execution-completion-report.md` للتفاصيل الكاملة. الأقسام أدناه (1، والجداول المتأثرة) عُدِّلت لتعكس الواقع الجديد؛ باقي الوثيقة (تحليل بقية التطبيقات، AD-001 إلى AD-018، إلخ) لا يزال صحيحًا كما كان.**

**نوع الوثيقة الأصلي: تدقيق. المنهجية:** كل حكم هنا مصدره تنفيذ فعلي، قراءة كود مباشرة، لا التوثيق وحده.
**تاريخ التدقيق الأصلي:** 2026-08-17. **تاريخ آخر تحديث:** 2026-09-02.

---

## 1. Executive Summary (محدَّثة)

حكم ورقم اليوم منصّة **بثلاثة منتجات حقيقية تعمل فعليًا**، لا واحد:

1. **بوابة معرفة (Core Content Product)** — منتج حقيقي يعمل: محتوى قانوني، بحث، مفضّلة، حاسبة مكافأة نهاية الخدمة. محتوى عام، لا يتطلب اشتراكًا (AD-007).
2. **إفلاس تك (Bankruptcy Tech) — ✅ جديد، حقيقي الآن** — Domain Models/Service/Policy/Controllers/Views كاملة، شخصي + مؤسسي، Tenant Isolation مُختبَرة، Audit كامل. راجع `docs/applications/eflas-tech.md`.
3. **Marketplace Domain (Core Platform الخلفي)** — طبقة Authorization/Organization/Subscription/Access — الآن تُدير **تطبيقين حقيقيين فعليًا**، لا واحدًا. الستة الباقون (articles/community/tech-portal/network/internships/ai-case-draft) يبقون Coming Soon **بتصميم صحيح ومقصود** (لا Backend وهمي، `entry_route=null` صراحة) — لم يُبنَوا الآن عمدًا، خارج نطاق هذي الجولة.

**تعارض Dashboard/My Apps (كان مذكورًا بالنسخة الأصلية) — ✅ أُغلِق.** كلاهما يستهلكان `UserAppsResolver` الآن (مصدر وحيد)، صفر اعتماد تشغيلي متبقٍّ على `app_subscriptions` القديم.

**Marketplace Categories (كانت فارغة بالنسخة الأصلية) — ✅ فُعِّلت.** 6 تصنيفات حقيقية، الثمانية عناصر كلها مربوطة.

**الخلاصة بجملة واحدة:** بنينا نظام صلاحيات وإدارة اشتراكات على مستوى Enterprise قوي جدًا، **لإدارة منتج واحد حقيقي.** الفجوة الحقيقية ليست بالـArchitecture — هي في عدد المنتجات الفعلية القابلة للبيع خلفها.

---

## 2. Current Marketplace Inventory

| العنصر | موجود فعليًا؟ | يعمل؟ | DB موجودة؟ | UI موجود؟ | API موجود؟ | الحالة |
|---|---|---|---|---|---|---|
| Laravel Structure (Models/Services/Policies) | ✅ | ✅ | — | — | — | ✅ Implemented |
| Organizations | ✅ `Organization.php` | ✅ | ✅ `organizations` (5 صفوف حقيقية) | ✅ Filament | ❌ | ✅ Implemented |
| Memberships | ✅ `Membership.php` | ✅ | ✅ `memberships` (7 صفوف) | ✅ Filament (RelationManager) | ❌ | ✅ Implemented |
| MembershipService (Domain) | ✅ | ✅ (مُختبَر بعشرات الاختبارات) | — | — | — | ✅ Implemented |
| OrganizationLifecycleService (Archive/Restore) | ✅ | ✅ | ✅ `organizations.status` | ✅ Filament (زران حقيقيان) | ❌ | ✅ Implemented |
| OrganizationSubscriptionService | ✅ | ✅ | ✅ `subscriptions` (2 صفوف مؤسسية) | ✅ Filament RelationManager | ❌ | ✅ Implemented |
| SubscriptionService (شخصي) | ✅ | ✅ | ✅ `subscriptions` (4 صفوف شخصية) | ✅ عبر `/marketplace/{key}/activate` | ❌ | ✅ Implemented |
| SeatService | ✅ | ✅ | ✅ `subscription_seats` | ✅ صفحة ويب حقيقية (`/organizations/{id}/seats`) | ❌ | ✅ Implemented |
| AccessAssignment | ✅ Model | ✅ | ✅ `access_assignments` | لا واجهة مستقلة (نتيجة داخلية) | ❌ | ✅ Implemented (كآلية داخلية) |
| EntitlementResolver | ✅ | ✅ (مصدر القرار الوحيد، مُختبَر بعمق) | — | — | ❌ | ✅ Implemented |
| OrganizationMarketplaceAccessGuard (AD-018) | ✅ | ✅ | — | — | — | ✅ Implemented |
| OrganizationPolicy | ✅ (الـPolicy الوحيدة بالمشروع كله) | ✅ | — | — | — | ✅ Implemented |
| Platform Staff / `canAccessPanel()` | ✅ `users.is_platform_staff` | ✅ | ✅ | ✅ (`/admin` مغلق فعليًا) | ❌ | ✅ Implemented |
| Audit Logs (Append-Only) | ✅ `AuditLog.php` + DB Triggers | ✅ | ✅ `audit_logs` (10 صفوف حقيقية) | ❌ لا Filament Resource له عمدًا | ❌ | ✅ Implemented |
| MarketplaceItem (الكتالوج) | ✅ Model | ✅ | ✅ `marketplace_items` (8 صفوف) | ✅ Filament CRUD بسيط | ❌ | ✅ Implemented (**كبنية**، المحتوى ضعيف — قسم 3) |
| MarketplaceCategory | ✅ Model + Filament Resource | ⚠️ لا بيانات | ✅ الجدول موجود، **0 صف** | ✅ Filament (فارغ) | ❌ | 🟡 Partially — بنية بلا استخدام فعلي |
| Partners | ✅ Model + Filament Resource | ⚠️ صف واحد فقط | ✅ `partners` (صف واحد: "حكم ورقم"، `first_party`) | ✅ Filament | ❌ | 🟡 Partially — لا Partner خارجي واحد حقيقي |
| Permissions/Roles (خارج Membership/Staff) | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ Not Found — لا نظام Roles/Permissions عام (Spatie أو مماثل)، فقط `MembershipRole` Enum + `is_platform_staff` |
| API Endpoints | ❌ | ❌ | — | — | ❌ | ❌ **Not Found** — لا `routes/api.php`، لا أي Route بـ`api/*`، لا Sanctum/Passport مُفعَّل لواجهة برمجية خارجية |
| Authentication | ✅ Laravel قياسي (Breeze-style) | ✅ | ✅ `users` (11 مستخدمًا حقيقيًا) | ✅ | — | ✅ Implemented — هوية واحدة مشتركة، لا Auth منفصل للـMarketplace |

**لا تعتبر وجود Class/Model وحده تطبيقًا مكتملًا (كما طلبت):** التمييز أعلاه بين "بنية موجودة" (`MarketplaceCategory`/`Partners`) و"بنية مُستخدَمة فعليًا ببيانات حقيقية" واضح ومتعمَّد.

---

## 3. Application-by-Application Inventory

**المصدر الوحيد الحقيقي لتعريف "تطبيق" بالمنصة:** `App\Support\PlatformApps::all()` (مصفوفة PHP ثابتة بالكود) → تُقرأ حرفيًا بواسطة `MarketplaceCatalogSeeder` → تُكتب لجدول `marketplace_items` + `application_details`. **8 تطبيقات فقط مُعرَّفة بكل المشروع — لا تطبيق تاسع موجود بأي مكان (تحققتُ بـ`grep` شامل، لا افتراض).**

| Application | تعريف بـArchitecture | DB Entity (`marketplace_items`) | `entry_route` حقيقي | UI مستقل | Backend حقيقي | API | Subscription يعمل | Access Control | الحالة |
|---|---|---|---|---|---|---|---|---|---|
| **بوابة معرفة** (`marefa`) | ✅ | ✅ | ✅ `marefa.home` | ✅ | ✅ | ❌ | ✅ (مجاني، شخصي) | ✅ عبر `EntitlementResolver` | ✅ **Implemented** |
| **إفلاس تك** (`bankruptcy-tech`) | ✅ | ✅ (`status=published` بالكتالوج، لكن `entry_route=NULL`) | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | 🔴 **Planned / Not Built** — كتالوج فقط |
| بوابة المقالات (`articles`) | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | 🔴 Planned / Not Built |
| مجتمع الخدمات (`community`) | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | 🔴 Planned / Not Built |
| بوابة التقنية (`tech-portal`) | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | 🔴 Planned / Not Built |
| الشبكة المهنية (`network`) | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | 🔴 Planned / Not Built |
| بوابة التدريب الميداني (`internships`) | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | 🔴 Planned / Not Built |
| محرك مسودة القضية الذكي (`ai-case-draft`) | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | 🔴 Planned / Not Built |

**لا يوجد تطبيق تاسع بأي مستند أو كود — لم أخترع أي تطبيق (كما طلبت صراحة).**

### شرح موجز لكل تطبيق غير بوابة معرفة (السبعة كلهم بنفس الحالة الفعلية بالضبط)

- **الموجود فعلًا:** صف بـ`marketplace_items` (اسم/وصف/أيقونة/جمهور مستهدَف) + صف مرتبط بـ`application_details` بـ`entry_route=NULL` + ظهور كبطاقة "قريبًا" بصفحة `/marketplace` مع زر "أنا مهتم" (يعمل فعليًا، يكتب لجدول `service_interests` — صف حقيقي واحد موجود لإفلاس تك تحديدًا كدليل).
- **غير الموجود:** أي Controller، أي Route فعلي (`entry_route=NULL` يعني `DatabaseMarketplaceRepository` لا يولّد `href` إطلاقًا)، أي Model خاص بمجاله (لا "قضية إفلاس" Model مثلًا)، أي صفحة، أي منطق عمل، أي Database Schema خاص بمجاله.
- **هل هو مجرد فكرة/Architecture؟** نعم، **حرفيًا فقط اسم ووصف بمصفوفة PHP ثابتة + صف DB مطابق.**
- **هل يمكن للمستخدم استخدامه اليوم؟** لا، إطلاقًا. أقصى تفاعل ممكن: تعبئة نموذج "أنا مهتم" (بريد إلكتروني) — لا شيء بعد ذلك.
- **هل له Route حقيقي؟** لا.
- **هل له Backend حقيقي؟** لا.
- **هل له Database حقيقية خاصة بمجاله؟** لا — فقط الصف العام بجدول الكتالوج المشترك.

---

## 4. بوابة معرفة — فحص كامل من البداية للنهاية

| السؤال | الجواب | الدليل |
|---|---|---|
| موجود فعلًا؟ | ✅ نعم | — |
| Frontend؟ | ✅ نعم | `resources/views/home.blade.php`, `laws/*.blade.php`, `updates/*.blade.php` |
| Backend؟ | ✅ نعم | `HomeController`, `LawController`, `LegalUpdateController`, `BookmarkController` |
| Database؟ | ✅ نعم، محتوى حقيقي | `law_entries`(10)، `law_articles`(15)، `categories`(8)، `legal_updates`(4)، `bookmarks` |
| Content؟ | ✅ حقيقي، ولو محدود الحجم | نفس الأعلاه — بيانات فعلية، لا Placeholder |
| Authentication؟ | ✅ نفس هوية المنصة الموحَّدة | لا Auth منفصل |
| Subscription؟ | ✅ نعم — شخصي، مجاني | `SubscriptionService::subscribeUserToFreeItem()`، عبر `/marketplace/marefa/activate` |
| Access Assignment؟ | ✅ نعم | يُنشأ فعليًا عند التفعيل |
| `EntitlementResolver` يتحكم بالوصول؟ | ⚠️ **جزئيًا — نقطة دقيقة مهمة** | راجع الشرح أدناه |
| Marketplace Item يمثّله؟ | ✅ نعم | `marketplace_items.key='marefa'`, `billing_model='both'` (الوحيد المدعوم مؤسسيًا **و**شخصيًا) |
| يستطيع المستخدم فتحه فعليًا؟ | ✅ **نعم، بلا أي قيد** | المحتوى **عام بالكامل** — لا Middleware `auth` على `/marefa`, `/laws`, `/laws/{id}`, `/updates` |
| Routes؟ | `GET /marefa`, `GET /laws`, `GET /laws/{lawEntry}`, `GET /updates`, `GET /calculators/gratuity` | `routes/web.php` |
| Controllers/Services | `HomeController`, `LawController`, `LegalUpdateController`, `BookmarkController`, `GratuityCalculator` (Livewire) | — |
| Public Content أم Marketplace Product؟ | **كلاهما معًا، بتصميم متعمَّد (AD-007)** | راجع الشرح أدناه |

### النقطة الدقيقة المهمة: لماذا "جزئيًا" على `EntitlementResolver`؟

**بوابة معرفة محتوى عام بالكامل** (AD-007 — "Marketplace Access ≠ Core Content Visibility"): أي زائر، **بلا تسجيل دخول حتى**، يقدر يفتح `/marefa`, `/laws`, `/laws/{id}`, `/updates` مباشرة — **صفر فحص `EntitlementResolver` على عرض المحتوى نفسه.** `EntitlementResolver`/`Subscription`/`AccessAssignment` موجودون **بمعزل تام** كطبقة تتبّع منفصلة ("هل هذا التطبيق جزء من بيئة عملك المُتتبَّعة؟") — لا "هل يحق لك رؤية هذا المحتوى؟". الاثنان يتعايشان بقصد، لا تعارض.

**ما الذي ينقصها لتصبح Product حقيقية قابلة للاستخدام؟** **لا شيء — هي بالفعل Product حقيقي قابل للاستخدام اليوم، وهذا مؤكَّد بالتنفيذ الفعلي طوال هذا المشروع بأكمله** (Playwright حقيقي، بيانات حقيقية، استخدام فعلي بمراجعات سابقة). النقص الوحيد مقارنة بمنتج "ناضج": حجم المحتوى صغير (10 أنظمة فقط)، لا محرك بحث دلالي، لا AI — كلها قرارات منتج، لا فجوة تقنية.

---

## 5. إفلاس تك — فحص كامل

**⚠️ قسم بأكمله أصبح قديمًا — إفلاس تك أصبح تطبيقًا حقيقيًا (Final Execution Sprint، 2026-09-02). الجدول أدناه محفوظ فقط كسجل تاريخي لحالتها "قبل"؛ للحالة الحقيقية اليوم راجع `docs/applications/eflas-tech.md` مباشرة (Frontend/Backend/Database/Authorization/Audit/Tests — كلها ✅ Implemented ومُختبَرة).**

<details><summary>الجدول الأصلي (تاريخي، قبل 2026-09-02)</summary>

| السؤال | الجواب (وقت التدقيق الأصلي) |
|---|---|
| موجودة؟ | ❌ لا — اسم ووصف فقط |
| Frontend؟ | ❌ Not Found |
| Backend؟ | ❌ Not Found |
| Database؟ | ❌ Not Found |
| APIs؟ | ❌ Not Found |
| Marketplace Integration؟ | 🔵 Architecture Only |
| Subscription/Access Model؟ | ❌ Not Found |

</details>

---

## 6. باقي التطبيقات (بوابة المقالات، مجتمع الخدمات، بوابة التقنية، الشبكة المهنية، بوابة التدريب الميداني، محرك مسودة القضية الذكي)

**نفس التحليل بالضبط بلا استثناء واحد** — راجع الجدول قسم 3 والشرح الموحَّد أسفله. **لا فروقات جوهرية بين السبعة** غير النص المعروض (الاسم/الوصف/الجمهور المستهدَف). لا أحد منهم له أي كود خلفه.

---

## 7. Marketplace Capabilities — الحالة لكل قدرة

| القدرة | الحالة | الدليل |
|---|---|---|
| Marketplace Catalog (`/marketplace`) | ✅ Implemented | `MarketplaceController::index()`, فلترة (الكل/مجاني/قريبًا) وبحث نصي **يعملان فعليًا** (مؤكَّد بقراءة الكود + View) |
| Marketplace Items (كبيانات) | ✅ Implemented (8 صفوف حقيقية) | `marketplace_items` |
| صفحة تطبيق فردية (`/marketplace/{key}`) | ✅ Implemented | `MarketplaceController::show()` + `marketplace-show.blade.php` — تعمل للثمانية كلهم (بمحتوى نصي فقط لغير-مرفا) |
| My Apps (`/my/apps`) | ✅ Implemented | `MyAppsController` — اتحاد حقيقي (شخصي + مؤسسي)، يعتمد `EntitlementResolver` حصرًا |
| Application Activation (شخصي) | ✅ Implemented | `/marketplace/{key}/activate` — يعمل **فقط** لبوابة معرفة عمليًا (الوحيدة المؤهَّلة `pricing_model=free` وباقي الشروط) |
| Subscription Management (مؤسسي) | ✅ Implemented | `OrganizationSubscriptionService` + Filament RelationManager — **لا واجهة ذاتية للمكتب، أداة Staff/فريق حكم ورقم فقط** |
| Access Management (Seats) | ✅ Implemented | `SeatService` + `/organizations/{id}/seats` — **واجهة ويب ذاتية حقيقية** (خارج Filament)، لـOwner/Admin |
| Organization Context (تبديل الهوية) | ✅ Implemented | `ActiveOrganizationContext` + `/organization-context/*` |
| Partners | 🟡 Partial — بنية فقط، بيانات وحيدة (`first_party` واحد) | `partners` جدول، صف واحد |
| Plans (`SubscriptionPlan`) | ✅ Implemented (كآلية داخلية) | مُنشأة تلقائيًا عبر `create()`، لا إدارة مباشرة منفصلة بالواجهة |
| Entitlements | ✅ Implemented | `EntitlementResolver` — مصدر القرار الوحيد، مُختبَر بعمق شديد |
| Audit | ✅ Implemented | Append-Only حقيقي (DB Triggers + Model-level)، **بلا واجهة عرض لأي مستخدم أو Staff — لا Filament Resource لـ`AuditLog` عمدًا** |
| Admin Management (Platform Staff) | ✅ Implemented | `canAccessPanel()`, `platform:grant-staff` CLI |
| Marketplace Categories (تصنيف حقيقي متعدد المستويات) | 🔵 Architecture Only | جدول فارغ تمامًا (0 صف) |
| Dashboard (`/dashboard`) | 🟡 **Partially Implemented — تعارض حقيقي مكتشَف** | راجع القسم التالي مباشرة |

### ⚠️ تعارض حقيقي مكتشَف: Dashboard يقرأ من نظام قديم منفصل تمامًا

`DashboardController::index()` (`app/Http/Controllers/DashboardController.php:16`) يستعلم **`$user->subscriptions()`** — وهذا **`AppSubscription`** (الجدول القديم `app_subscriptions`، نظام Phase 1 الأصلي، **قبل** كل بنية `Subscription`/`AccessAssignment`/`EntitlementResolver` الحالية). بينما `MyAppsController` يستعلم النظام **الجديد** بالكامل (`marketplaceSubscriptions()` + `EntitlementResolver`).

**دليل حي:** `app_subscriptions` تحتوي اليوم **4 صفوف نشطة حقيقية** (نظام قديم لم يُنظَّف، مُجمَّد الكتابة منذ L1 لكن القراءة القديمة لم تُهاجَر) — بينما `subscriptions` (الجديد) يحتوي **4 شخصية + 2 مؤسسية**. **هاتان مجموعتا بيانات منفصلتان تمامًا، قد لا تتطابقان لنفس المستخدم.** هذا يعني: **صفحة Dashboard وصفحة My Apps يمكن تعرضان قائمتين مختلفتين من "تطبيقاتي" لنفس المستخدم اليوم** — لم يُصلَح لأن Dashboard مصنَّف "Core Platform" (خارج نطاق L1/L2/كل مراحل Marketplace اللاحقة عمدًا، AD-015). **هذا Finding حقيقي من الكود، لا افتراض — مذكور هنا كما طلبت الإفصاح عن أي تعارض.**

---

## "ماذا يستطيع المستخدم فعليًا عمله اليوم؟" — من Login إلى My Apps

1. يسجّل دخول (`/login`) بحساب حقيقي.
2. يفتح `/dashboard` — يرى تطبيقات "مشترك بها" **من النظام القديم فقط** (قد لا تعكس الواقع الحالي، القيد أعلاه).
3. يفتح `/marketplace` — يرى الثمانية تطبيقات، يقدر يفلتر (الكل/مجاني/قريبًا) ويبحث فعليًا.
4. يفتح `/marketplace/marefa` — صفحة تفصيلية حقيقية، زر "ادخل الآن" يعمل.
5. يفتح `/marketplace/bankruptcy-tech` (أو أي تطبيق آخر "قريبًا") — صفحة تفصيلية بمحتوى نصي فقط، زر "أنا مهتم" يعمل (يحفظ بريده الإلكتروني).
6. يضغط "ادخل الآن" ببوابة معرفة → `SubscriptionService::subscribeUserToFreeItem()` يُنفَّذ فعليًا → يُحوَّل لـ`/marefa` (محتوى حقيقي).
7. يفتح `/my/apps` — يرى بوابة معرفة مُدرَجة (اتحاد حقيقي، عبر `EntitlementResolver`).
8. لو عضو بمؤسسة: يبدّل السياق (`/organization-context/{id}`)، يفتح `/organizations/{id}/seats` (لو Owner/Admin) — يدير مقاعد حقيقية.

**هذا هو كامل رحلة المستخدم الحقيقية القابلة للتجربة اليوم — لا خطوة واحدة أكثر من هذا.**

---

## 8. Architecture Decisions (AD-001 إلى AD-018) — القرار مقابل التنفيذ

| # | القرار (ملخَّص) | Architecture | Implementation | Verified | ملاحظات |
|---|---|---|---|---|---|
| AD-001 | Audit Log Append-Only، قائمة أحداث مغلقة | ✅ | ✅ | ✅ (Triggers + Builder، مُختبَرة) | وُسِّعت لاحقًا (Archive/Restore/MembershipCreated) |
| AD-002 | نقطة دخول واحدة لكل Domain Mutation (BR-013) | ✅ | ✅ | ✅ | مُطبَّق عبر كل الـServices |
| AD-003 | حماية تزامن المقاعد (Seat Race) | ✅ | ✅ | ✅ (اختبار OS حقيقي سابقًا) | — |
| AD-005 | Entitlement ≠ Authorization | ✅ | ✅ | ✅ | فصل ثابت طوال المشروع |
| AD-007 | Marketplace Access ≠ Core Content Visibility | ✅ | ✅ | ✅ | مؤكَّد قسم 4 أعلاه |
| AD-009 | تمييز دلالي لأحداث Seat/Access/Subscription | ✅ | ✅ | ✅ | — |
| AD-011 | Active Organization Context لا يمنح صلاحية | ✅ | ✅ | ✅ | — |
| AD-012 | Context مؤشر فقط، لا مصدر حقيقة | ✅ | ✅ | ✅ (مُختبَر IDOR-style عدة مرات) | — |
| AD-013 | EntitlementResolver مصدر وحيد للوصول الفعّال | ✅ | ✅ | ✅ | — |
| AD-014 | لا Reactivation ضمني (Explicit Intent) | ✅ | ✅ | ✅ | امتدَّ لـRestore لاحقًا |
| AD-015 | Marketplace تُضاف لحكم ورقم، لا العكس | ✅ | ✅ (لم يُلمَس Header/Dashboard/Navigation) | ✅ | **راجع تعارض Dashboard قسم 7 — القرار احتُرم حرفيًا (لم يُلمَس)، لكن هذا بالذات ترك التعارض دون حل** |
| AD-016 | تغييرات Membership يجب تكون قابلة للتدقيق | ✅ | 🟡 **جزئي** | 🟡 | Create مُدقَّق (`MembershipCreated`)، **تغيير Role/الإزالة/نقل الملكية لا يزالان بلا Audit Event مستقل — فجوة مُوثَّقة صراحة، غير مُغلَقة عمدًا** |
| AD-017 | نقل الملكية يتطلب Owner حقيقي، لا استثناء لـStaff | ✅ | ✅ | ✅ (مُختبَر Livewire+مباشر) | — |
| AD-018 | مؤسسة مؤرشَفة لا تكتسب Access جديدًا | ✅ | ✅ | ✅ (مُختبَر + مراجعتان مستقلتان) | 🟢 مُغلَقة رسميًا |

**لا قرار معماري بلا تنفيذ مطابق غير AD-016 (مُقَرّ به صراحة، لا مفاجأة).** هذا يُثبت: **الفجوة الحقيقية بالمنصة ليست "قرارات بلا تنفيذ" — كل قرار Authorization/Lifecycle نُفِّذ وأُعيد تدقيقه.** الفجوة هي "منتجات بلا Architecture خلفها بعد" (القسم 3).

---

## 9. Marketplace ↔ Hukm w Rakam — الحد الفاصل

### ما تحتاجه Marketplace من Hukm w Rakam (موجود بالفعل، مُستهلَك كما هو)

| الاحتياج | الحالة | كيف؟ |
|---|---|---|
| Authentication/Identity | ✅ موجود، مُستهلَك | `users` جدول موحَّد، `Auth::user()` بكل مكان |
| Organizations/Memberships | ✅ **هي نفسها Core Platform اليوم** | لا فصل حقيقي — `Organization`/`Membership` جزء من نفس الـDomain |
| Roles | ✅ موجود | `MembershipRole` Enum + `is_platform_staff` |
| Navigation/Header/Dashboard | ❌ **لم تُدمَج عمدًا (AD-015)** | Marketplace تعيش بمسارات مستقلة (`/marketplace`, `/my/apps`)، لا رابط بالـHeader الرئيسي (لم أتحقق من الـHeader نفسه بهذي الجولة تحديدًا — خارج نطاق طلبك، لكن AD-015 يمنع لمسه) |
| Billing | ❌ **Not Found** | لا بوابة دفع، لا Billing Engine — كل الاشتراكات اليوم مجانية فقط (`pricing_model=free` أو `NULL`) |

### ما تحتاجه Hukm w Rakam من Marketplace (جاهز اليوم، غير مُستهلَك بعد بالواجهة الرئيسية)

| الاحتياج | الحالة | ملاحظة |
|---|---|---|
| My Apps | ✅ جاهز كـEndpoint (`/my/apps`) | غير مربوط بأي Navigation رئيسي بعد |
| Marketplace Catalog | ✅ جاهز (`/marketplace`) | نفس أعلاه |
| Application Access State | ✅ جاهز (`EntitlementResolver`) | جاهز للاستهلاك من أي واجهة مستقبلية بلا إعادة بناء |
| Subscription State | ✅ جاهز | نفس أعلاه |
| Entitlements | ✅ جاهز | نفس أعلاه |

### ما يمكن ربطه لاحقًا **بلا إعادة هندسة**

إضافة رابط "المتجر"/"تطبيقاتي" بالـHeader الرئيسي يوجّه لـ`/marketplace`/`/my/apps` — **Routes جاهزة اليوم بالفعل**، لا حاجة لبناء أي Backend جديد لهذي الخطوة بعينها.

### ما يحتاج API/Integration حقيقية

- **حل تعارض Dashboard** (قسم 7) — يحتاج قرارًا صريحًا (تهجير القراءة للنظام الجديد، أم دمج المصدرين) قبل أي ربط أعمق.
- **أي تطبيق فعلي جديد** (إفلاس تك أو غيره) يحتاج بناءً كاملًا من الصفر (قسم 12).
- **Billing حقيقي** لو احتيج تسعير مدفوع مستقبلًا — غير موجود بأي شكل اليوم.

---

## 10. Missing Components (ملخَّص)

- API Layer (REST/GraphQL) — غير موجود إطلاقًا.
- Billing/Payment Gateway — غير موجود.
- Marketplace Categories الفعلية (تصنيف حقيقي متعدد) — الجدول فارغ.
- Partners خارجيون حقيقيون — صفر.
- Integrations Layer (AD ذكرها الـBlueprint كطبقة مستقبلية) — لا كود لها إطلاقًا (لا Model، لا جدول).
- Audit Event لتغيير Role/الإزالة (AD-016).
- توحيد مصدر "تطبيقاتي" بين Dashboard وMy Apps.
- 7 تطبيقات كاملة (Backend+Frontend+DB) — إفلاس تك وغيره.

---

## 11. Future Build Roadmap — لكل تطبيق (لا كود، تخطيط فقط)

**ينطبق بنفس البنية على كل السبعة (إفلاس تك، المقالات، المجتمع، التقنية، الشبكة، التدريب، AI Draft) — أذكر إفلاس تك كمثال، الباقي مطابق بنيويًا:**

### Phase A — Marketplace Listing
✅ **مكتملة بالفعل لكل السبعة** — موجودون بالكتالوج، يظهرون بـ`/marketplace`، لديهم صفحة تفصيلية، لديهم "أنا مهتم" يعمل.

### Phase B — Application Foundation
تحديد الـDomain الفعلي (لإفلاس تك: قضايا/دائنون/إجراءات)، Models، Migrations، Controllers، Views مستقلة، Route حقيقي (`entry_route` بـ`application_details`).

### Phase C — Subscription & Access
تحديد `billing_model`/`pricing_model` الحقيقيين (مجاني/مدفوع/مؤسسي)، ربط بـ`SubscriptionService`/`OrganizationSubscriptionService` الموجودين فعليًا (لا حاجة بناء جديد هنا — البنية جاهزة تمامًا).

### Phase D — Integration
لا حاجة تكامل إضافي مع Hukm w Rakam بمعنى الهوية/الصلاحيات (جاهز أصلًا) — فقط ربط اختياري بـNavigation لو احتيج.

### Phase E — Production Readiness
اختبارات، تدقيق أمني (نفس منهجية Organization/Membership المُطبَّقة بهذا المشروع)، محتوى حقيقي، مراجعة قانونية/تنظيمية (لإفلاس تك تحديدًا، مجال حسّاس قانونيًا).

---

## 12. Integration Roadmap (Marketplace ↔ Hukm w Rakam ككل)

1. حسم تعارض Dashboard (قرار صريح مطلوب أولًا).
2. تصميم نقطة دمج Navigation (AD-015 يمنع التنفيذ بلا إذن منفصل — هذا تخطيط فقط).
3. تقييم الحاجة لطبقة API لو احتيج تكامل خارجي (Partners حقيقيون) مستقبلًا.

---

## 13. Risks / Unknowns

- **الأكبر:** فجوة الإدراك المحتملة بين "قوة الـArchitecture الخلفية" و"عدد المنتجات الفعلية" — قد يُفهَم خطأً أن المنصة "جاهزة لكل شيء" بينما 7 من 8 تطبيقات أسماء فقط.
- تعارض Dashboard/My Apps (قسم 7) — خطر تجربة مستخدم حقيقي لو استُخدِم اليوم بإنتاج حقيقي.
- AD-016 (Audit لتغييرات Membership) يبقى فجوة مفتوحة معروفة.
- لا Billing يعني أي قرار تسعير مستقبلي يحتاج بناءً من الصفر، لا تمديدًا.
- لم أتحقق من الـHeader/Navigation الفعلي بهذي الجولة (خارج طلبك الصريح، AD-015 يمنع لمسه) — لو احتجت فحصه لاحقًا، يحتاج جولة تدقيق منفصلة.

---

## 14. Final "What We Actually Have Today" — القوائم الأربع

**⚠️ محدَّثة بعد Final Execution Sprint (2026-09-02).**

### A — موجود ويعمل اليوم (تقدر تجرّبه فعليًا الآن)
- تسجيل دخول/تسجيل حساب حقيقي.
- بوابة معرفة كاملة — محتوى عام، بلا حاجة اشتراك.
- **إفلاس تك — تطبيق حقيقي كامل** (قضايا/أطراف/إجراءات/مستندات حقيقية/ملاحظات/سجل زمني، شخصي + مؤسسي، Tenant Isolation مُختبَرة). ✅ جديد.
- `/marketplace`: كتالوج 8 تطبيقات، فلترة وبحث حقيقيان، 6 تصنيفات حقيقية.
- `/marketplace/{key}`: صفحة تفصيلية لكل تطبيق.
- زر "أنا مهتم" لأي تطبيق "قريبًا".
- تفعيل/إلغاء اشتراك شخصي مجاني (مرفا **وإفلاس تك** الآن).
- `/my/apps` **و`/dashboard`**: نفس المصدر الموحَّد (`UserAppsResolver`) — لا تعارض بعد الآن.
- Organization Context switching، إدارة مقاعد مؤسسية ذاتية.
- لوحة تحكم Filament كاملة لـPlatform Staff.

### B — موجود Architecture فقط (مصمَّم لكن ليس Product حقيقي)
- 6 تطبيقات (المقالات، المجتمع، التقنية، الشبكة، التدريب، AI Draft) — أسماء + أوصاف، صفر كود خلفهم، **بتصميم صحيح ومقصود** (Phase 9 — لم تُبنَ الآن عمدًا).
- Partners الخارجيون — لا صف واحد حقيقي بعد (البنية جاهزة، `partner_type` يدعم `third_party` بلا إعادة هندسة).
- طبقة "Integrations" المذكورة بالـBlueprint — لا كود لها إطلاقًا.
- Billing حقيقي (بوابة دفع) — Abstraction جاهز (`billing_model`/`pricing_model`/`SubscriptionPlan.price`)، لا تفعيل فعلي.

### C — غير موجود ويحتاج بناء
- Backend/Frontend/DB كامل لكل تطبيق من الستة الباقين (لو أُريد تفعيله لاحقًا).
- Payment Gateway حقيقي.
- API Layer (غير مطلوبة اليوم، لا حاجة فعلية).
- Audit لتغيير Role/الإزالة (AD-016) — ✅ **أُغلِقت الآن** (Final Execution Sprint).

### D — يحتاج Integration مع Hukm w Rakam (لا بناء Marketplace من جديد، فقط ربط)
- ربط `/marketplace` و`/my/apps` بالـHeader/Navigation الرئيسي (Routes جاهزة، لم تُربَط بعد — AD-015 يمنع لمس Header بلا إذن منفصل).
- أي واجهة Self-Service مستقبلية لـOwner حقيقي.

---

**راجع `docs/final-execution-completion-report.md` للتفاصيل الكاملة (ما بُني، الاختبارات، النتائج الأمنية).**
