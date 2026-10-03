<?php

namespace Database\Seeders;

use App\Models\AppSubscription;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class AppSubscriptionSeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(AppSubscription::class, 'app_subscriptions.json');
    }
}
