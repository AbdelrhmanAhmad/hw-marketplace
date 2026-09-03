<?php

namespace Database\Seeders;

use App\Models\ApplicationDetail;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceItem;
use App\Models\Partner;
use App\Support\PlatformApps;
use Illuminate\Database\Seeder;

/**
 * يعبّئ marketplace_items (وapplication_details) من App\Support\PlatformApps::all()
 * حرفيًا — هذا هو الضمان الفعلي لتطابق Parity Check، لا نسخ يدوي للبيانات
 * (البيانات تُقرأ من نفس المصدر القديم مباشرة وقت التنفيذ، لا تُكتب مرتين).
 *
 * راجع docs/marketplace-implementation-specification.md قسم AB (Phase 1a).
 *
 * Final Execution Sprint — أضاف Categories حقيقية (Phase 10) + بيانات
 * إفلاس تك الصحيحة الآن بعد تحوّلها لتطبيق حقيقي (billing_model='both'،
 * أصبح مدعومًا شخصيًا ومؤسسيًا؛ pricing_model='free'، لا بوابة دفع بعد —
 * توثيق صريح، لا ادّعاء دفع غير موجود). كل التطبيقات الستة الأخرى بلا
 * تغيير — تبقى Coming Soon بصدق (`entry_route=null`).
 */
class MarketplaceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $partner = Partner::firstOrCreate(
            ['name' => 'حكم ورقم'],
            ['partner_type' => 'first_party'],
        );

        // Phase 10 — تصنيف حقيقي مبني على طبيعة كل تطبيق فعليًا، لا فئات وهمية.
        $categories = [
            'legal-services' => MarketplaceCategory::firstOrCreate(['slug' => 'legal-services'], ['name' => 'الخدمات القانونية']),
            'financial-services' => MarketplaceCategory::firstOrCreate(['slug' => 'financial-services'], ['name' => 'الخدمات المالية']),
            'knowledge-content' => MarketplaceCategory::firstOrCreate(['slug' => 'knowledge-content'], ['name' => 'المحتوى المعرفي']),
            'community-networking' => MarketplaceCategory::firstOrCreate(['slug' => 'community-networking'], ['name' => 'المجتمع والشبكات']),
            'tech-solutions' => MarketplaceCategory::firstOrCreate(['slug' => 'tech-solutions'], ['name' => 'الحلول التقنية']),
            'training-development' => MarketplaceCategory::firstOrCreate(['slug' => 'training-development'], ['name' => 'التدريب والتطوير']),
        ];

        $itemCategoryMap = [
            'marefa' => 'legal-services',
            'bankruptcy-tech' => 'financial-services',
            'articles' => 'knowledge-content',
            'community' => 'community-networking',
            'tech-portal' => 'tech-solutions',
            'network' => 'community-networking',
            'internships' => 'training-development',
            'ai-case-draft' => 'tech-solutions',
        ];

        // إعادة تشغيل الـSeeder آمنة (Idempotent) — entry_route يُحدَّث دائمًا
        // من app_key ثابت بالكود (لا route() هنا، لتفادي أي استدعاء لروابط
        // قد لا تكون مسجَّلة وقت تشغيل Seeder بمعزل عن HTTP Kernel).
        $entryRoutes = [
            'marefa' => 'marefa.home',
            'bankruptcy-tech' => 'bankruptcy-tech.cases.index',
        ];

        // مرفا وإفلاس تك فقط يدعمان اشتراكًا مؤسسيًا فعليًا (both) — الستة
        // الباقون كتالوج بلا تطبيق خلفه، user_only افتراضيًا يكفي. **تحذير
        // مُستفاد فعليًا:** إسقاط `marefa` من هذي القائمة كان سيُعيد قيمتها
        // خطأً لـ'user_only' ويكسر مؤسسات حقيقية مشتركة بها فعليًا بقاعدة
        // البيانات — أي Override مستقبلي هنا يجب يحافظ على مرفا صراحة.
        $billingOverrides = [
            'marefa' => ['billing_model' => 'both', 'pricing_model' => 'free'],
            'bankruptcy-tech' => ['billing_model' => 'both', 'pricing_model' => 'free'],
        ];

        foreach (PlatformApps::all() as $app) {
            $billing = $billingOverrides[$app['key']] ?? [
                'billing_model' => 'user_only',
                'pricing_model' => ($app['free'] ?? false) ? 'free' : null,
            ];

            $item = MarketplaceItem::updateOrCreate(
                ['key' => $app['key']],
                [
                    'type' => 'application',
                    'partner_id' => $partner->id,
                    'category_id' => $categories[$itemCategoryMap[$app['key']]]->id,
                    'name' => $app['name'],
                    'tagline' => $app['tagline'],
                    'description' => $app['description'],
                    'icon' => $app['icon'],
                    'status' => 'published',
                    'billing_model' => $billing['billing_model'],
                    'pricing_model' => $billing['pricing_model'],
                    'compatibility' => $app['audiences'] ?? [],
                    'version' => '1.0',
                ],
            );

            ApplicationDetail::updateOrCreate(
                ['marketplace_item_id' => $item->id],
                ['entry_route' => $entryRoutes[$app['key']] ?? null],
            );
        }
    }
}
