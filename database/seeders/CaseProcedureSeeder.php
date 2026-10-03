<?php

namespace Database\Seeders;

use App\Models\CaseProcedure;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class CaseProcedureSeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(CaseProcedure::class, 'bankruptcy_case_procedures.json');
    }
}
