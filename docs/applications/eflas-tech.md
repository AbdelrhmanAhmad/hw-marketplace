# إفلاس تك (Bankruptcy Tech) — توثيق التطبيق

**الحالة: ✅ MVP حقيقي يعمل** (لا Demo، لا Prototype) — أول تطبيق Marketplace غير بوابة معرفة يخرج من Catalog Item إلى تطبيق فعلي. مبني على الكود الفعلي المُنفَّذ والمُختبَر (Final Execution Sprint)، لا افتراضات.

---

## 1. ماذا يفعل التطبيق

إدارة قضايا الإفلاس/إعادة الهيكلة المالية: إنشاء قضية، تتبّع الأطراف (مدين/دائن/أمين تفليسة)، إجراءات القضية بحالاتها، مستندات حقيقية (رفع/تنزيل)، ملاحظات، وسجل زمني كامل لكل ما يحدث بالقضية.

## 2. الوصول

| العنصر | القيمة |
|---|---|
| `marketplace_items.key` | `bankruptcy-tech` |
| `billing_model` | `both` (شخصي + مؤسسي) |
| `pricing_model` | `free` (لا بوابة دفع اليوم — راجع قسم 8) |
| `entry_route` | `bankruptcy-tech.cases.index` |
| المسار الأساسي | `/apps/bankruptcy-tech` |
| Marketplace | `/marketplace/bankruptcy-tech` |
| My Apps | يظهر تلقائيًا بعد التفعيل (`UserAppsResolver`) |

## 3. Domain Models

| Model | الجدول | العلاقة بالقضية |
|---|---|---|
| `BankruptcyCase` | `bankruptcy_cases` | الجذر — `organization_id` nullable (null = شخصية) |
| `CaseParty` | `bankruptcy_case_parties` | `hasMany` |
| `CaseProcedure` | `bankruptcy_case_procedures` | `hasMany` |
| `CaseDocument` | `bankruptcy_case_documents` | `hasMany` — تخزين حقيقي (`Storage::disk('local')`) |
| `CaseNote` | `bankruptcy_case_notes` | `hasMany` |

**لا Migration من أي نوع لأي جدول آخر بالمشروع** — عزل تام عن Organization/Membership/Subscription Domain (لا FK غير `organization_id`/`user_id` القياسيين).

## 4. Authorization ≠ Entitlement (فصل صريح، مُختبَر)

```
Entitlement (EnsureMarketplaceEntitlement middleware → EntitlementResolver)
    "يقدر يستخدم إفلاس تك أصلًا؟" — على مستوى المجموعة الكاملة (Route Group)

Authorization (BankruptcyCasePolicy، داخل BankruptcyCaseService)
    "يقدر يرى/يعدّل *هذي القضية بعينها*؟" — Owner/Admin (مؤسسي) أو صاحبها (شخصي) أو Staff
```

- **قضية شخصية:** صاحبها فقط (+ Platform Staff).
- **قضية مؤسسية:** أي عضو حقيقي بنفس المؤسسة يرى/يساهم، Owner/Admin فقط يديرون (تغيير الحالة).
- **Tenant Isolation:** مُختبَر عبر HTTP حقيقي (`BankruptcyCaseHttpTest`) — عضو مؤسسة له وصول فعّال حقيقي (Seat حقيقي) لا يقدر يرى قضية مؤسسة أخرى، حتى لو كان لديه Entitlement صحيح لتطبيق إفلاس تك بشكل عام.
- **IDOR:** إجراء تابع لقضية A لا يمكن التلاعب به عبر رابط قضية B (حتى لو المهاجم يملك B فعليًا) — مُختبَر، يرجع 404.
- **المستندات:** لا رابط عام مباشر — كل تنزيل يمر عبر Controller يتحقق من الصلاحية أولًا.

## 5. Audit

كل Mutation حساس يُسجَّل بـ`AuditLog` (نفس آلية AD-001 المُحصَّنة — Append-Only حقيقي، لا استثناء): `case_created`, `case_status_changed`, `case_party_added`, `case_procedure_added`, `case_procedure_status_changed`, `case_note_added`, `case_document_uploaded`. السجل الزمني بصفحة القضية يُبنى مباشرة من هذي السجلات — لا مصدر بيانات مواز.

## 6. الاختبارات

| الملف | العدد | يغطي |
|---|---|---|
| `BankruptcyCaseServiceTest.php` | 15 | إنشاء/تغيير حالة/أطراف/إجراءات/مستندات، Tenant Isolation على مستوى Policy |
| `BankruptcyCaseHttpTest.php` | 8 | Entitlement Gate، رحلة HTTP كاملة، IDOR، عزل مؤسسي عبر HTTP حقيقي (مع إثبات إيجابي مسبق — الفاعل مخوَّل فعليًا قبل إثبات الرفض) |

## 7. ما لم يُبنَ عمدًا (لا ادّعاء)

- **لا API** — واجهة Blade كافية لـMVP، لا حاجة فعلية لواجهة برمجية اليوم.
- **لا بوابة دفع** — `pricing_model='free'` صراحة، لا رسوم اليوم.
- **لا إشعارات فعلية (Email/SMS)** — البنية (Audit Trail + Timeline) جاهزة لتُستهلَك من نظام إشعارات مستقبلي، لم يُبنَ بعد.
- **لا Workflow معقَّد للإجراءات** (تسلسل إلزامي، موافقات) — حالة بسيطة (pending/in_progress/completed) تكفي لـMVP.

## 8. الخطوة التالية لو احتيجت (لا تنفيذ الآن)

Billing حقيقي (لو تقرَّر تسعير مدفوع) — يستهلك نفس Abstraction الموجود (`billing_model`/`pricing_model` على `MarketplaceItem`) بلا حاجة إعادة هندسة الـDomain، راجع `docs/marketplace-final-architecture.md` قسم Billing Abstraction.
