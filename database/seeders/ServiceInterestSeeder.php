<?php

namespace Database\Seeders;

use App\Models\ServiceInterest;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class ServiceInterestSeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(ServiceInterest::class, 'service_interests.json');
    }
}
