<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class BookmarkSeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedTableFromJson('bookmarks', 'bookmarks.json');
    }
}
