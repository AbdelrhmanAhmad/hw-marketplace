<?php

namespace Database\Seeders;

use App\Models\TechServiceRequest;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class TechServiceRequestSeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(TechServiceRequest::class, 'tech_service_requests.json');
    }
}
