<?php

namespace Database\Seeders;

use App\Models\Subscription;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class SubscriptionSeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(Subscription::class, 'subscriptions.json');
    }
}
