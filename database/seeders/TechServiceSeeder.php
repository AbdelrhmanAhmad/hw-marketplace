<?php

namespace Database\Seeders;

use App\Models\TechService;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class TechServiceSeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(TechService::class, 'tech_services.json');
    }
}
