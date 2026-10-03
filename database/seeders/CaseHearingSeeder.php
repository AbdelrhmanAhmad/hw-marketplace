<?php

namespace Database\Seeders;

use App\Models\CaseHearing;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class CaseHearingSeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(CaseHearing::class, 'bankruptcy_case_hearings.json');
    }
}
