<?php

namespace Database\Seeders;

use App\Models\ApplicationDetail;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class ApplicationDetailSeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(ApplicationDetail::class, 'application_details.json');
    }
}
