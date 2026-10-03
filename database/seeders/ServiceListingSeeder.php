<?php

namespace Database\Seeders;

use App\Models\ServiceListing;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class ServiceListingSeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(ServiceListing::class, 'service_listings.json');
    }
}
