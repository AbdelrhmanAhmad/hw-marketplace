<?php

namespace Database\Seeders;

use App\Models\Organization;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(Organization::class, 'organizations.json');
    }
}
