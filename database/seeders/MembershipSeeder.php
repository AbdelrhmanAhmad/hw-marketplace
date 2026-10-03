<?php

namespace Database\Seeders;

use App\Models\Membership;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class MembershipSeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(Membership::class, 'memberships.json');
    }
}
