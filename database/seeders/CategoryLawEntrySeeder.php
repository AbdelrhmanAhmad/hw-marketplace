<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class CategoryLawEntrySeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedTableFromJson('category_law_entry', 'category_law_entry.json');
    }
}
