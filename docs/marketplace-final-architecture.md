# Marketplace — العمارة النهائية (بعد Final Execution Sprint)

مبني على الكود الفعلي المُنفَّذ والمُختبَر — لا توثيق سابق لتنفيذ لاحق.

---

## 1. الطبقات

```
Identity (users)
   │
   ├── Personal Context ──────────────┐
   │                                   │
   └── Organizations → Memberships ────┤
                                        │
                                   Subscriptions (شخصي/مؤسسي، Subscription واحد
                                        │        polymorphic: subscriber_type=user|organization)
                                        │
                                   SubscriptionPlan (seat_limit)
                                        │
                                   SubscriptionSeat (مؤسسي فقط)
                                        │
                                   AccessAssignment ← EntitlementResolver يقرأ من هنا حصرًا
                                        │
                                   ┌────┴────┐
                                   │         │
                              بوابة معرفة   إفلاس تك
                          (محتوى عام،    (BankruptcyCasePolicy
                           AD-007)        مستقل تمامًا عن Entitlement)
```

## 2. Marketplace Product Model — لا Hardcoded Logic لكل تطبيق

كل تطبيق = صف واحد بـ`marketplace_items` + صف `application_details`:

| الحقل | الغرض |
|---|---|
| `key` | معرّف فريد ثابت (لا يتغيّر أبدًا بعد الإنشاء) |
| `name`/`tagline`/`description`/`icon` | عرض بالكتالوج |
| `category_id` | تصنيف (Phase 10 — 6 تصنيفات حقيقية اليوم) |
| `partner_id` | الناشر (`Partner.partner_type`: `first_party`/`third_party`) |
| `status` | حالة الكتالوج نفسها (`published`) — **لا تعني تلقائيًا "مُطلَق"** |
| `billing_model` | `user_only`/`organization_only`/`both` |
| `pricing_model` | `free`/`null` (Billing Abstraction، قسم 4) |
| `application_details.entry_route` | **هذا وحده يحدد "مُطلَق فعليًا أم Coming Soon"** — `null` = Coming Soon، قيمة حقيقية = رابط دخول فعلي |

**`DatabaseMarketplaceRepository` يحسب `status` المعروض للمستخدم (`available`/`soon`) من وجود `entry_route` فقط** — لا شرط آخر، لا Hardcoding لأسماء تطبيقات محدَّدة بمنطق العرض نفسه (الاستثناء الوحيد لاسم تطبيق محدَّد موجود بـ`MarketplaceCatalogSeeder` — طبقة **بيانات**، لا طبقة **منطق**، فرق متعمَّد).

## 3. Filament/Marketplace لا تحتوي Business Logic خاصة بأي تطبيق

`MarketplaceController`/`MyAppsController`/`UserAppsResolver` لا تعرف شيئًا عن "قضايا الإفلاس" أو أي مفهوم خاص بإفلاس تك — تتعامل معه كـ`MarketplaceItem` عام مثل أي عنصر آخر. كل منطق إفلاس تك (Domain Models/Service/Policy/Controllers/Views) معزول بالكامل بمساره الخاص (`app/**/BankruptcyTech`, `bankruptcy_case*` tables) — إزالته بالكامل لا يكسر Marketplace نفسها.

## 4. Billing Abstraction

**لا بوابة دفع حقيقية اليوم — هذا القسم يوثّق Abstraction موجود بالفعل، لا خطة مستقبلية وهمية.**

`billing_model` × `pricing_model` يدعمان اليوم:

| billing_model | المعنى |
|---|---|
| `user_only` | اشتراك شخصي فقط |
| `organization_only` | اشتراك مؤسسي فقط |
| `both` | كلاهما (مرفا، إفلاس تك) |

| pricing_model | المعنى |
|---|---|
| `free` | مجاني — `SubscriptionService::subscribeUserToFreeItem()` يعمل مباشرة |
| `null` | غير مُسعَّر بعد (Coming Soon فقط اليوم) |

**التوسّع المستقبلي (Per Seat/Paid) لا يحتاج تغيير Domain:** `SubscriptionPlan.seat_limit`/`price`/`billing_cycle` **موجودة بالفعل بالـSchema** (غير مُستخدَمة اليوم، `price=null` دائمًا) — إضافة تسعير حقيقي = تفعيل هذي الحقول + بوابة دفع خارجية، لا إعادة هندسة `Subscription`/`Seat`/`AccessAssignment`/`EntitlementResolver`.

## 5. Integration Boundary — Marketplace ↔ Hukm w Rakam

راجع `docs/marketplace-current-state-inventory.md` قسم 9 (لم يتغيّر) — **لا تكامل جديد أُضيف بهذي الجولة**، Header/Dashboard-Navigation لم يُلمَسا (AD-015).

**نقطة توضيح واحدة أُضيفت:** `DashboardController`/`MyAppsController` أصبحا يستهلكان **نفس** `UserAppsResolver` — أي واجهة Hukm w Rakam مستقبلية تحتاج "تطبيقات المستخدم" تستهلك نفس هذي الخدمة، لا استعلامًا موازيًا (AD-013).

## 6. API

**لا API عامة موجودة، ولا حاجة فعلية لها اليوم** (Blade يخدم كل شيء). لو احتيجت مستقبلًا (تطبيق جوال، تكامل خارجي)، `EnsureMarketplaceEntitlement` (Middleware) قابل لإعادة الاستخدام حرفيًا خلف بادئة `api/` بلا تعديل — مصمَّم عمدًا بلا افتراض HTML.

## 7. AD-019 (جديد) — Marketplace Product Model Contract

**قيد مُسجَّل (Final Execution Sprint):** أي تطبيق Marketplace جديد **يجب** يُمثَّل حصرًا بصف `marketplace_items` + `application_details`، بلا أي حقل/Special-case بمنطق Marketplace نفسه (`MarketplaceController`/`MyAppsController`/`UserAppsResolver`/`DatabaseMarketplaceRepository`). أي Authorization خاص بتطبيق معيَّن يعيش بـPolicy/Service خاصة به فقط، أي Entitlement يمر عبر `EntitlementResolver`/`EnsureMarketplaceEntitlement` الموجودين حصرًا — لا فحص مواز. إفلاس تك أول تطبيق يُثبت هذا العقد عمليًا.
