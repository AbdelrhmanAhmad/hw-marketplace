<?php

namespace Database\Seeders;

use App\Models\MarketplaceItem;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class MarketplaceItemSeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(MarketplaceItem::class, 'marketplace_items.json');
    }
}
