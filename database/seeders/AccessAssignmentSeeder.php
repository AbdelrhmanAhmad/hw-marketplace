<?php

namespace Database\Seeders;

use App\Models\AccessAssignment;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class AccessAssignmentSeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(AccessAssignment::class, 'access_assignments.json');
    }
}
