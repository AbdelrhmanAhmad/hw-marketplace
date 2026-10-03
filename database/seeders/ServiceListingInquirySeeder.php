<?php

namespace Database\Seeders;

use App\Models\ServiceListingInquiry;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class ServiceListingInquirySeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(ServiceListingInquiry::class, 'service_listing_inquiries.json');
    }
}
