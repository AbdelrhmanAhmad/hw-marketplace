<?php

namespace Database\Seeders;

use App\Models\SubscriptionSeat;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class SubscriptionSeatSeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(SubscriptionSeat::class, 'subscription_seats.json');
    }
}
