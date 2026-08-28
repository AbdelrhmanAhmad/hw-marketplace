# Audit Log Integrity Hardening — تصميم (يليه تنفيذ بنفس الجلسة بتصريحك)

**الحالة:** تصميم، ثم تنفيذ فور اعتمادك على هذا القسم من التصميم — لا L2 قبل إغلاق هذي الوثيقة بالكامل واعتمادك الصريح.
**السبب:** حادثة حقيقية (`docs/audit-log-integrity-incident-report.md`) — أمر Tinker تجريبي شغّلته أثناء تصحيح صياغة استعلام JSON استخدم `AuditLog::query()->delete()` (Mass Delete عبر Query Builder) على قاعدة التطوير الحية، فحذف 30 سجل `audit_logs` تاريخي بالكامل. حماية AD-001 الحالية (Override على `update()`/`delete()` بمستوى الـInstance فقط بـ`app/Models/AuditLog.php`) **لم توقف هذا** لأنها لا تغطي مسارات Query Builder أصلًا.
**المرجع:** AD-001 (`marketplace-architecture-blueprint.md` §Audit Minimal Layer) · `legacy-subscription-l2-safe-migration-specification.md` §8/§11.

---

## سبب الفجوة

`app/Models/AuditLog.php` يحتوي:
```php
public function update(array $attributes = [], array $options = []): bool
{
    throw new LogicException(...);
}

public function delete(): ?bool
{
    throw new LogicException(...);
}
```

هذي دوال **Instance-level** — تُستدعى فقط عندما يكون عندك كائن Model فعلي بالذاكرة (`$log->delete()`). لكن معظم عمليات الحذف/التعديل الجماعي بـLaravel **لا تمر بهذا المسار إطلاقًا**:

- `AuditLog::query()->delete()` أو `AuditLog::where(...)->delete()` → يُنفَّذ عبر `Illuminate\Database\Eloquent\Builder::delete()`، دالة مختلفة تمامًا، لا علاقة لها بدالة الـInstance.
- `DB::table('audit_logs')->delete()` → لا يمر بطبقة Eloquent إطلاقًا، يذهب مباشرة لـ`Illuminate\Database\Query\Builder` على مستوى قاعدة البيانات الخام.
- `AuditLog::destroy($ids)` → دالة ساكنة، تُنفِّذ الحذف عبر مسارات داخلية قد لا تحترم Override الـInstance حسب نسخة Laravel.
- Eloquent Model Events (`static::deleting()`/`static::updating()`) **لا تُطلَق أصلًا** لعمليات Mass Update/Delete عبر Query Builder — هذا سلوك موثَّق رسميًا بـLaravel، لا خطأ إعداد. لذلك أي حل يعتمد على Model Events وحدها (`booted()`) **سيفشل بنفس الطريقة** التي فشل بها الـInstance Override.

**الخلاصة:** AD-001 كان يحمي من "استخدام Eloquent بالطريقة المتوقَّعة بالخطأ" فقط — لم يكن يحمي من "تجاوز Eloquent الطبيعي (Mass Operations) أو تجاوز Eloquent بالكامل (Raw Query Builder)"، وهما بالضبط المسارين اللي طلبت تغطيتهما.

---

## Attack/Bypass Matrix — كل طريقة تعديل/حذف ممكنة

| # | المسار | يمر بـInstance Override الحالي؟ | يُطلِق Model Events؟ | الحالة قبل هذي الوثيقة |
|---|---|---|---|---|
| 1 | `$log->delete()` (Instance) | ✅ نعم | ✅ نعم | ✅ محمي فعليًا |
| 2 | `$log->update([...])` (Instance) | ✅ نعم | ✅ نعم | ✅ محمي فعليًا |
| 3 | `AuditLog::query()->delete()` / `AuditLog::where(...)->delete()` | ❌ لا | ❌ لا | 🔴 **غير محمي — هذا بالضبط ما حدث بالحادثة** |
| 4 | `AuditLog::where(...)->update([...])` | ❌ لا | ❌ لا | 🔴 غير محمي |
| 5 | `AuditLog::destroy($ids)` | ❌ لا (حسب المسار الداخلي) | ⚠️ جزئيًا (نسخة-معتمِد) | 🔴 غير محمي بثقة |
| 6 | `AuditLog::truncate()` (Eloquent) | ❌ لا | ❌ لا | 🔴 غير محمي |
| 7 | `DB::table('audit_logs')->delete()` (Raw Query Builder) | ❌ لا (لا علاقة بـEloquent إطلاقًا) | ❌ لا | 🔴 **غير محمي — أخطر مسار، يتجاوز Eloquent بالكامل** |
| 8 | `DB::table('audit_logs')->update([...])` | ❌ لا | ❌ لا | 🔴 غير محمي |
| 9 | `DB::statement('DELETE FROM audit_logs')` / SQL خام | ❌ لا | ❌ لا | 🔴 غير محمي |
| 10 | Filament Admin Path | لا يوجد — **تأكَّد بالفحص (قسم "أثر التغيير" أدناه): لا `AuditLogResource` موجود بالمشروع كاملًا اليوم** | — | ✅ غير موجود أصلًا (لا مسار = لا خطر حاليًا) |
| 11 | Repository/Service داخلي | لا يوجد Repository لـ`AuditLog` — الاستخدام الشرعي الوحيد بكل الكودبيس: `AuditLog::create()` من ثلاث خدمات فقط (`SubscriptionService`, `OrganizationSubscriptionService`, `SeatService`)، مؤكَّد بـgrep شامل | — | ✅ لا مسار خفي موجود |
| 12 | `TRUNCATE TABLE audit_logs` (DDL خام، محرك MySQL/Postgres مستقبلًا) | ❌ لا | ❌ لا | ⚠️ **حد بنيوي — راجع "حدود الحماية"** |
| 13 | `Schema::dropIfExists('audit_logs')` (Migration جديدة تحذف الجدول بالكامل) | خارج نطاق أي حماية Runtime | — | ⚠️ فعل إداري متعمَّد بمستوى Migration/DDL، مقبول كحدّ بنيوي، راجع أدناه |

---

## الخيار المعماري المختار — طبقتان مستقلتان (Defense in Depth حقيقي، لا وهمي)

**المبدأ:** لا حل واحد يغطي كل المسارات أعلاه. الحل الصحيح طبقتان **مستقلتان تمامًا** بلا اعتماد إحداهما على الأخرى:

### الطبقة 1 — SQLite Database Trigger (السلطة النهائية، تغطي المسارات 3-9 بلا استثناء)

Migration جديدة تُنشئ Triggers على مستوى قاعدة البيانات نفسها:
```sql
CREATE TRIGGER prevent_audit_logs_update
BEFORE UPDATE ON audit_logs
BEGIN
    SELECT RAISE(ABORT, 'audit_logs is append-only — UPDATE rejected at database level (AD-001)');
END;

CREATE TRIGGER prevent_audit_logs_delete
BEFORE DELETE ON audit_logs
BEGIN
    SELECT RAISE(ABORT, 'audit_logs is append-only — DELETE rejected at database level (AD-001)');
END;
```

**لماذا هذي الطبقة السلطة النهائية:** الـTrigger يعمل بمستوى محرك قاعدة البيانات نفسه — **لا علاقة له بـPHP، لا بـLaravel، لا بـEloquent إطلاقًا.** أي طريقة تصل لقاعدة البيانات (Eloquent، Query Builder خام، حتى عميل SQLite مباشر مثل `sqlite3 database.sqlite`) ستُرفَض بنفس الرسالة. **هذا يحقق شرطك الحرفي: "حتى لو استخدم مطور هذا الأسلوب بالخطأ، يفشل التنفيذ"** — الفشل هنا ليس مشروطًا بانضباط كود PHP، بل مضمون ببنية قاعدة البيانات ذاتها.

### الطبقة 2 — Custom Eloquent Query Builder (تجربة مطوّر أفضل، تغطي المسارات 1-8 بمستوى Eloquent بدون لمس قاعدة البيانات)

```php
// app/Models/Builders/AppendOnlyBuilder.php
class AppendOnlyBuilder extends \Illuminate\Database\Eloquent\Builder
{
    public function update(array $values): int
    {
        throw new LogicException('audit_logs جدول Append-only — لا تعديل جماعي مسموح (AD-001).');
    }

    public function delete(): mixed
    {
        throw new LogicException('audit_logs جدول Append-only — لا حذف جماعي مسموح (AD-001).');
    }
}

// app/Models/AuditLog.php
public function newEloquentBuilder($query): AppendOnlyBuilder
{
    return new AppendOnlyBuilder($query);
}
```

**لماذا طبقة مستقلة ثانية رغم وجود الـTrigger:** الـTrigger يرفض العملية **بعد** وصولها لقاعدة البيانات — يعني `AuditLog::query()->delete()` سيُنفَّذ، يصل للـSQL، **ثم** يُرفَض برسالة SQL خام (`QueryException`). الطبقة الثانية ترفضه **قبل** حتى بناء الـSQL، برسالة PHP واضحة (`LogicException` بنفس أسلوب الحماية الحالية) — تجربة مطوّر أفضل (رسالة أوضح، Stack Trace أقصر)، وسرعة أعلى (لا Round-trip لقاعدة البيانات). **لا تُغني إحداهما عن الأخرى** — الطبقة الثانية Eloquent-only فتفشل صمتًا أمام `DB::table()` الخام (المسار الأخطر، رقم 7 بالجدول)، والطبقة الأولى (Trigger) هي الضمان الوحيد الشامل لكل المسارات.

**كلا الطبقتين لا تُغيّران دالتي الـInstance الحاليتين (`update()`/`delete()` بـ`AuditLog.php`)** — تبقيان كما هما، طبقة ثالثة إضافية، لا استبدال.

---

## حدود الحماية (صريحة، لا إخفاء)

1. **`TRUNCATE TABLE` بمحرك MySQL/PostgreSQL مستقبلًا (لا SQLite الحالي):** بعض محركات قواعد البيانات (تحديدًا MySQL) تُعامل `TRUNCATE` كعملية DDL (تُسقِط الجدول وتُعيد إنشاءه) لا DML — **لا تُطلِق DELETE Triggers إطلاقًا في تلك المحركات.** بيئة التطوير الحالية SQLite (`DELETE FROM` هو المسار الوحيد للحذف الجماعي، بلا `TRUNCATE` DDL منفصل — محمي بالكامل بالـTrigger أعلاه). **لو انتقل المشروع مستقبلًا لمحرك آخر (MySQL/Postgres) للاتصال بـhw.sa الحقيقية، هذي الحماية يجب تُعاد صياغتها بصيغة الـTrigger المكافئة لذاك المحرك، والتحقق صراحة إن `TRUNCATE` محظور أيضًا (بمنح صلاحيات قاعدة بيانات تمنع `TRUNCATE` على مستخدم التطبيق، أو بحماية إضافية مستوى Application Role) — هذا **ليس مُنفَّذًا الآن لعدم انطباقه على SQLite**، مسجَّل كمتطلب صريح لأي Migration مستقبلي لمحرك إنتاج.**
2. **`Schema::dropIfExists('audit_logs')` عبر Migration جديدة:** فعل إداري متعمَّد بمستوى كتابة كود/تشغيل Migration — خارج نطاق أي حماية Runtime بالتعريف (نفس فئة "حذف الكود المصدري نفسه"). **الحماية الوحيدة الممكنة هنا إجرائية لا تقنية:** مراجعة الكود (Code Review) قبل أي Migration تلمس `audit_logs`، تمامًا كأي جدول حسّاس آخر — لا حل برمجي يمنع مطوّرًا يكتب Migration حذف متعمَّد.
3. **وصول مباشر لقاعدة البيانات بصلاحيات SUPERUSER/DBA خارج التطبيق:** أي حماية بمستوى Trigger قابلة للتعطيل من طرف له صلاحية `DROP TRIGGER` — هذا حد بنيوي مقبول عالميًا (لا نظام حماية Application-level يمنع مدير قاعدة بيانات كامل الصلاحيات)، **مطابق تمامًا لعبارتك:** "ما الذي يمكن تجاوزه عمدًا فقط عبر database-level mechanism" — التعطيل المتعمَّد يتطلب فعلًا بمستوى إدارة قاعدة البيانات نفسها، لا بمستوى كود تطبيق عادي أو حتى Tinker.
4. **الحادثة الفعلية (30 سجلًا) لن تتكرر بنفس الآلية** — لكن هذي الحماية **لا تُعيد** السجلات المفقودة (راجع `audit-log-integrity-incident-report.md`)، فقط تمنع تكرار نفس نوع الحادثة مستقبلًا.

---

## خطة الاختبارات

كل اختبار يثبت **أمرين معًا**: (أ) العملية تفشل (Exception محدَّدة)، (ب) البيانات لم تتغيّر إطلاقًا (`assertDatabaseHas`/مقارنة عدد الصفوف قبل/بعد).

| # | المسار | الفشل المتوقَّع |
|---|---|---|
| 1 | Instance `delete()` | `LogicException` (الحماية الحالية، إعادة تأكيد) |
| 2 | Instance `update()` | `LogicException` (الحماية الحالية، إعادة تأكيد) |
| 3 | `AuditLog::query()->delete()` | `LogicException` من `AppendOnlyBuilder` (الطبقة الجديدة) |
| 4 | `AuditLog::where(...)->update([...])` | `LogicException` من `AppendOnlyBuilder` |
| 5 | `AuditLog::destroy($id)` | `LogicException` (يمر بمسار Instance أو Builder حسب التطبيق الداخلي — يُختبَر فعليًا لا افتراضًا) |
| 6 | `DB::table('audit_logs')->delete()` (تجاوز Eloquent بالكامل) | `QueryException` من الـTrigger — **الاختبار الأهم**، يثبت الطبقة الأولى (Database-level) تعمل بمعزل عن أي حماية PHP |
| 7 | `DB::table('audit_logs')->update([...])` | `QueryException` من الـTrigger |
| 8 | `DB::statement('DELETE FROM audit_logs')` (SQL خام مباشر) | `QueryException` من الـTrigger — يثبت الحماية تعمل حتى بمعزل تام عن أي طبقة Laravel |
| 9 | تأكيد عدم وجود مسار Filament/Admin لـ`audit_logs` | فحص ثابت (لا Route/Resource موجود)، لا اختبار سلوكي مطلوب لعدم وجود سطح هجوم أصلًا |
| 10 | Regression — `AuditLog::create()` الطبيعي ما زال يعمل | يجب ينجح بلا أي استثناء (التحقق إن الحماية لا تمنع الاستخدام الشرعي الوحيد) |

---

## أثر التغيير على الكود الحالي

- **Migration جديدة واحدة** (`create_audit_logs_append_only_triggers`) — لا تعدّل Schema الجدول نفسه، فقط تضيف Triggers.
- **ملف جديد واحد** (`app/Models/Builders/AppendOnlyBuilder.php`).
- **تعديل سطر واحد بـ`app/Models/AuditLog.php`** (إضافة `newEloquentBuilder()`) — لا حذف لأي كود موجود.
- **صفر تعديل على أي كود يكتب لـ`AuditLog`** — الاستخدام الشرعي الوحيد بكل الكودبيس (`create()` من ثلاث خدمات، مؤكَّد بقسم Bypass Matrix أعلاه) لا يمر بـ`update()`/`delete()` إطلاقًا، فلا يتأثر بهذا التغيير بأي شكل.
- **صفر تعديل على Filament** — لا `AuditLogResource` موجود، لا شيء يتأثر.

---

## Rollback Plan

| الخطوة | كيف نرجع |
|---|---|
| Migration الـTriggers | `down()` تُشغِّل `DROP TRIGGER IF EXISTS prevent_audit_logs_update;` و`DROP TRIGGER IF EXISTS prevent_audit_logs_delete;` — عكس تام، صفر أثر على البيانات (الـTriggers نفسها لا تخزّن بيانات) |
| `AppendOnlyBuilder` + `newEloquentBuilder()` | حذف الملف + التراجع عن السطر المضاف بـ`AuditLog.php` — الكود المحذوف فقط، لا بيانات تأثرت |
| **لا خطر بيانات بأي اتجاه** — هذي حماية إضافية بحتة (منع كتابة)، ليست Migration بيانات، التراجع عنها لا يفقد ولا يغيّر أي سجل موجود |

---

## القرار

بانتظار اعتمادك الصريح على هذا التصميم قبل أي كود — التنفيذ (Migration + Builder + الاختبارات العشرة) يبدأ فقط بعد موافقتك، بنفس الجلسة إن رغبت.
