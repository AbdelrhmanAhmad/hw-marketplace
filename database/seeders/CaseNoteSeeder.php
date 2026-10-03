<?php

namespace Database\Seeders;

use App\Models\CaseNote;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class CaseNoteSeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(CaseNote::class, 'bankruptcy_case_notes.json');
    }
}
