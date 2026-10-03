<?php

namespace Database\Seeders;

use App\Models\TrainingApplication;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class TrainingApplicationSeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(TrainingApplication::class, 'training_applications.json');
    }
}
