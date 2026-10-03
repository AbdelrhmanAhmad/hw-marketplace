<?php

namespace Database\Seeders;

use App\Models\CaseEmployee;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class CaseEmployeeSeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(CaseEmployee::class, 'bankruptcy_case_employees.json');
    }
}
