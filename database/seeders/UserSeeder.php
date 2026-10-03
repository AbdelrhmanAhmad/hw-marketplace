<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(User::class, 'users.json');
    }
}
