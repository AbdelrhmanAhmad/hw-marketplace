<?php

namespace Database\Seeders;

use App\Models\ArticleCategory;
use Illuminate\Database\Seeder;

/** بوابة المقالات — تصنيف ثابت مبدئي (قرار #3 بالخطة) — لا واجهة إدارة تصنيفات منفصلة الآن. */
class ArticleCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'قانوني', 'slug' => 'legal'],
            ['name' => 'مالي', 'slug' => 'financial'],
            ['name' => 'محاسبي', 'slug' => 'accounting'],
            ['name' => 'عام', 'slug' => 'general'],
        ] as $category) {
            ArticleCategory::updateOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
