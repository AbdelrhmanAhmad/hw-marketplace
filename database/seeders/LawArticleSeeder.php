<?php

namespace Database\Seeders;

use App\Models\LawArticle;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class LawArticleSeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(LawArticle::class, 'law_articles.json');
    }
}
