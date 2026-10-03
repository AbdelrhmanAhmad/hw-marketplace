<?php

namespace Database\Seeders;

use App\Models\MarketplaceCategory;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class MarketplaceCategorySeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(MarketplaceCategory::class, 'marketplace_categories.json');
    }
}
