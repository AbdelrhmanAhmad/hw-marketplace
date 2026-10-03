<?php

namespace Database\Seeders;

use App\Models\Category;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(Category::class, 'categories.json');
    }
}
