<?php

namespace Database\Seeders;

use App\Models\BankruptcyCase;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class BankruptcyCaseSeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(BankruptcyCase::class, 'bankruptcy_cases.json');
    }
}
