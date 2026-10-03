<?php

namespace Database\Seeders;

use App\Models\CaseDocument;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class CaseDocumentSeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(CaseDocument::class, 'bankruptcy_case_documents.json');
    }
}
