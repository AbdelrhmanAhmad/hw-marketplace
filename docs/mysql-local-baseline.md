# MySQL local baseline (hw_marketplace)

> تاريخ: **2026-10-03** — التطوير المحلي على MySQL مع seeders Eloquent رسمية.

## القرار

- الاتصال الافتراضي: `DB_CONNECTION=mysql` / قاعدة `hw_marketplace`.
- خط الأساس: seeders حسب الموديل تحت `database/seeders/` + fixtures JSON تحت `database/seeders/data/`.
- لا استيراد mysqldump في مسار الـ seed — المخطط عبر migrations، والبيانات عبر Eloquent/`updateOrCreate` (أو insert للجداول append-only/pivots).

## إعداد سريع

```bash
# .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hw_marketplace
DB_USERNAME=root
DB_PASSWORD=

php artisan migrate:fresh --seed
```

## هيكل الـ seeders

| الطبقة | المسار |
|--------|--------|
| المدخل | `DatabaseSeeder` يستدعي seeders الموديلات بالترتيب الصحيح لـ FK |
| الموديل | مثل `UserSeeder`, `MarketplaceItemSeeder`, `BankruptcyCaseSeeder`, … |
| البيانات | `database/seeders/data/{table}.json` |
| المشترك | `Concerns/SeedsFromJson` — تحميل JSON + `updateOrCreate` + مزامنة `AUTO_INCREMENT` |

`MarketplaceCatalogSeeder` يبقى للاختبارات/Parity (يبني الكتالوج من `PlatformApps`) ولا يُستدعى من `DatabaseSeeder` الافتراضي حتى لا تتعارض IDs مع علاقات الاشتراكات في الـ fixtures.

`LawEntriesSeeder` أصبح alias يستدعي seeders المعرفة (Category / LawEntry / …).

`BankruptcyTechDemoSeeder` يبقى اختيارياً يدوياً ولا يُدرج في المسار الافتراضي.

## Audit triggers

المايجريشن `2026_08_12_113323_create_audit_logs_append_only_triggers` driver-aware (MySQL `SIGNAL` / SQLite `RAISE`).  
`AuditLogSeeder` يستخدم insert فقط (لا update) احترامًا لـ append-only.

## ملف العميل

`hukm_w_rakam_dump.sql` في جذر المشروع مرجع خارجي من العميل فقط — ليس مسار التشغيل المحلي.
