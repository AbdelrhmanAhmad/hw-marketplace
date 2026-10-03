<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(SubscriptionPlan::class, 'subscription_plans.json');
    }
}
