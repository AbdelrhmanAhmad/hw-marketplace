<?php

namespace Database\Seeders;

use App\Models\Article;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class ArticleSeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(Article::class, 'articles.json');
    }
}
