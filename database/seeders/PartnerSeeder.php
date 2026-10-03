<?php

namespace Database\Seeders;

use App\Models\Partner;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class PartnerSeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(Partner::class, 'partners.json');
    }
}
