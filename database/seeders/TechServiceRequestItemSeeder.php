<?php

namespace Database\Seeders;

use App\Models\TechServiceRequestItem;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class TechServiceRequestItemSeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(TechServiceRequestItem::class, 'tech_service_request_items.json');
    }
}
