# Marketplace home — Glassmorphism (قابل للتراجع)

> تاريخ: **2026-10-03** (محدَّث: ضغط مسافات + هيدر/فوتر + باترنات Core)

## القرار

رئيسية سوق التطبيقات بهوية حكم ورقم + Glass خفيف:

- ألوان Core: `#198754` / `#00793A` / `#072C1B`
- مسافات مضغوطة (بدون فراغات ضخمة بين الأقسام)
- هيدر/فوتر أقرب لواجهة زائر Core (تنقّل + زر Secondary)
- باترنات منقولة من Core إلى `public/images/patterns/`
- لوحة الهيرو اليسرى = معاينة متجر (عنوان + شبكة تطبيقات واضحة) بدل وسوم شفافة ضعيفة
- قسم القيم = بطاقات فاتحة مستقلة داخل المحتوى (لا شريط داكن يختلط بالفوتر)

## الأصول المنسوخة من Core

| المصدر (hokm-wa-raqm) | الوجهة |
|------------------------|--------|
| `hw-dots-corner-a/b.png` | `public/images/patterns/` |
| `about/dots-a/b.png` | `public/images/patterns/` |
| `hokm-w-raqam-hero-bg-wide.webp` | `public/images/patterns/hero-bg-wide.webp` |
| `logo1.png` / `logo0a.png` | `public/images/brand/` |

## التراجع

```env
MARKETPLACE_HOME_VARIANT=classic
```

```bash
php artisan config:clear
```
