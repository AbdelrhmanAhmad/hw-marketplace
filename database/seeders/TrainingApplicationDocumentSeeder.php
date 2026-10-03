<?php

namespace Database\Seeders;

use App\Models\TrainingApplicationDocument;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class TrainingApplicationDocumentSeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(TrainingApplicationDocument::class, 'training_application_documents.json');
    }
}
