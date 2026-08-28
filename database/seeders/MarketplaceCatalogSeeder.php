<?php

namespace Database\Seeders;

use App\Models\ApplicationDetail;
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
 */
class MarketplaceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $partner = Partner::firstOrCreate(
            ['name' => 'حكم ورقم'],
            ['partner_type' => 'first_party'],
        );

        // إعادة تشغيل الـSeeder آمنة (Idempotent) — entry_route يُحدَّث دائمًا
        // من app_key ثابت بالكود (لا route() هنا، لتفادي أي استدعاء لروابط
        // قد لا تكون مسجَّلة وقت تشغيل Seeder بمعزل عن HTTP Kernel).
        $entryRoutes = [
            'marefa' => 'marefa.home',
        ];

        foreach (PlatformApps::all() as $app) {
            $item = MarketplaceItem::updateOrCreate(
                ['key' => $app['key']],
                [
                    'type' => 'application',
                    'partner_id' => $partner->id,
                    'category_id' => null,
                    'name' => $app['name'],
                    'tagline' => $app['tagline'],
                    'description' => $app['description'],
                    'icon' => $app['icon'],
                    'status' => 'published',
                    'billing_model' => 'user_only',
                    'pricing_model' => ($app['free'] ?? false) ? 'free' : null,
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
