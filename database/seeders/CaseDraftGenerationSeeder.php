<?php

namespace Database\Seeders;

use App\Models\CaseDraftGeneration;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class CaseDraftGenerationSeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(CaseDraftGeneration::class, 'case_draft_generations.json');
    }
}
