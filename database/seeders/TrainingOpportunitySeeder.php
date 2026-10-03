<?php

namespace Database\Seeders;

use App\Models\TrainingOpportunity;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class TrainingOpportunitySeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(TrainingOpportunity::class, 'training_opportunities.json');
    }
}
