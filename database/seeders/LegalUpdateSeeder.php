<?php

namespace Database\Seeders;

use App\Models\LegalUpdate;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class LegalUpdateSeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(LegalUpdate::class, 'legal_updates.json');
    }
}
