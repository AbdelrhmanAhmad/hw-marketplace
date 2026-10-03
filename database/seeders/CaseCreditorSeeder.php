<?php

namespace Database\Seeders;

use App\Models\CaseCreditor;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class CaseCreditorSeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(CaseCreditor::class, 'bankruptcy_case_creditors.json');
    }
}
