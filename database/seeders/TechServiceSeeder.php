<?php

namespace Database\Seeders;

use App\Models\TechService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** بوابة التقنية — كتالوج أولي (المرحلة 1)، يُدار لاحقًا عبر Filament مباشرة. */
class TechServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ['title' => 'تصميم موقع إلكتروني احترافي', 'category' => 'تطوير مواقع', 'description' => 'تصميم وتطوير موقع تعريفي احترافي لمكتبك أو شركتك، متوافق مع الجوال ومحسّن لمحركات البحث.', 'price_note' => 'يبدأ من 4,500 ريال', 'sort_order' => 1],
            ['title' => 'متجر إلكتروني متكامل', 'category' => 'تطوير مواقع', 'description' => 'بناء متجر إلكتروني كامل مع بوابة دفع وإدارة مخزون، مناسب لتقديم خدماتك أو منتجاتك أونلاين.', 'price_note' => 'يبدأ من 9,000 ريال', 'sort_order' => 2],
            ['title' => 'تطبيق جوال (iOS وAndroid)', 'category' => 'تطبيقات جوال', 'description' => 'تطوير تطبيق جوال مخصص لمكتبك أو خدماتك المهنية، يدعم إشعارات الدفع وحسابات العملاء.', 'price_note' => 'يبدأ من 25,000 ريال', 'sort_order' => 3],
            ['title' => 'لوحة تحكم داخلية مخصصة', 'category' => 'تطبيقات جوال', 'description' => 'نظام إدارة داخلي مخصص لسير عمل مكتبك (عملاء، قضايا، فواتير) حسب احتياجك بالضبط.', 'price_note' => 'يُحدَّد بعد الاستشارة', 'sort_order' => 4],
            ['title' => 'استضافة وصيانة تقنية شهرية', 'category' => 'استضافة وصيانة', 'description' => 'استضافة موقعك أو تطبيقك على خوادم موثوقة، مع نسخ احتياطي دوري ودعم فني شهري.', 'price_note' => 'يبدأ من 300 ريال / شهريًا', 'sort_order' => 5],
            ['title' => 'تحسين محركات البحث (SEO)', 'category' => 'تسويق رقمي', 'description' => 'تحسين ظهور موقعك بنتائج البحث المتعلقة بخدماتك القانونية أو المالية.', 'price_note' => 'يبدأ من 1,500 ريال / شهريًا', 'sort_order' => 6],
        ];

        foreach ($services as $service) {
            TechService::updateOrCreate(
                ['slug' => Str::slug($service['title'])],
                $service + ['slug' => Str::slug($service['title']), 'is_published' => true],
            );
        }
    }
}
