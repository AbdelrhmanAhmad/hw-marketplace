# Final Execution Sprint — Baseline

## Current State (مؤكَّد بتشغيل فعلي)

- Suite: 242/242 قبل البدء. Dev DB: users=11, organizations=5, memberships=7, subscriptions=6, audit_logs=10, marketplace_items=8, marketplace_categories=0, partners=1.
- `/marketplace`, `/marketplace/{key}`, `/my/apps`, `/marketplace/{key}/activate`, `/organizations/{id}/seats`, `/admin`, Organization Context, Subscription, EntitlementResolver, Access Assignment — كلها تعمل فعليًا (مؤكَّد بالجرد السابق `marketplace-current-state-inventory.md`).
- **تعارض Dashboard/My Apps مؤكَّد بدليل حي جديد:** `marketplace:subscription-parity-check` يُظهر: 4 مستخدمين لهم صف `app_subscriptions` نشط، **واحد منهم (`user_id=6`) ألغى اشتراكه فعليًا بالنظام الجديد (`cancelled`)** بينما السجل القديم لا يزال يقول "نشط". Dashboard يقرأ القديم، My Apps يقرأ الجديد → تناقض حقيقي حي، لا افتراضي. **Residual migration gap = 0** (Parity Check رسمي) — لا حاجة لأي Migration بيانات، فقط تبديل مصدر قراءة Dashboard.
- بوابة معرفة: منتج حقيقي يعمل (10 أنظمة، 15 مادة، محتوى عام لا يحتاج اشتراكًا).
- إفلاس تك وباقي الستة: صف كتالوج فقط، `entry_route=NULL`.
- `MarketplaceItem`/`ApplicationDetail`/`Partner`/`MarketplaceCategory` — البنية **جاهزة بالفعل** لتمثيل أي Application (key/name/description/icon/category_id/partner_id/status/billing_model/pricing_model/compatibility) — لا حاجة إعادة تصميم Schema، فقط استخدامها.
- لا API Layer، لا Middleware مخصَّص غير `ValidateActiveOrganizationContext`.

## Blockers
**لا يوجد Blocker حقيقي يمنع التنفيذ.** لا قرار يغيّر Business Model، لا حذف بيانات مطلوب، كل المعلومات اللازمة مُستنتَجة من المشروع.

## What Will Be Built
1. إصلاح Dashboard (يقرأ من النظام الجديد، يطابق My Apps حرفيًا).
2. إفلاس تك — MVP حقيقي: Domain Models (BankruptcyCase/CaseParty/CaseProcedure/CaseDocument/CaseNote) + Service + Policy + Controllers + Views + Routes + ربط Marketplace/My Apps + Audit عبر `AuditLog` الموجود (أحداث جديدة بالقائمة المُوسَّعة، نفس نمط Archive/Restore/MembershipCreated السابق).
3. Marketplace Categories — بيانات حقيقية، مربوطة بالعناصر الثمانية.
4. Partner — توثيق صريح لقيمة `partner_type` (`first_party`/`third_party`) بلا اختراع شركاء وهميين.
5. Billing Abstraction — توثيق صريح لـ`billing_model`/`pricing_model` كنقطة توسّع مستقبلية (لا بوابة دفع وهمية).
6. AD-016 — إكمال Audit لتغيير Role/الإزالة (الفجوة المؤجَّلة سابقًا).
7. اختبارات جديدة + E2E حقيقي.
8. توثيق نهائي.

## What Will Not Be Rebuilt
- لا إعادة بناء Marketplace من الصفر — البنية الحالية (Organization/Membership/Subscription/Seat/EntitlementResolver/Policies) تُستخدَم كما هي.
- لا Header/Dashboard-redesign/Navigation جديد — فقط تصحيح مصدر بيانات Dashboard الحالي.
- لا بوابة معرفة تُعاد بناؤها.
- لا API عامة (غير مطلوبة لـMVP بواجهة Blade، سيُوثَّق السبب صراحة).
- لا بوابة دفع حقيقية.
- لا الستة تطبيقات الأخرى (تبقى Coming Soon بتصميم صحيح).

**التنفيذ يبدأ الآن.**
