<?php

namespace Database\Seeders;

use App\Models\LawEntry;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class LawEntrySeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(LawEntry::class, 'law_entries.json');
    }
}
