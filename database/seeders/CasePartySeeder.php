<?php

namespace Database\Seeders;

use App\Models\CaseParty;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class CasePartySeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(CaseParty::class, 'bankruptcy_case_parties.json');
    }
}
