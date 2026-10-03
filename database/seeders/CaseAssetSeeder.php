<?php

namespace Database\Seeders;

use App\Models\CaseAsset;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class CaseAssetSeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(CaseAsset::class, 'bankruptcy_case_assets.json');
    }
}
