<?php

namespace Database\Seeders;

use App\Models\ArticleAuthor;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class ArticleAuthorSeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(ArticleAuthor::class, 'article_authors.json');
    }
}
