<?php

namespace Database\Seeders;

use App\Models\ArticleCategory;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class ArticleCategorySeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(ArticleCategory::class, 'article_categories.json');
    }
}
