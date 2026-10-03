<?php

namespace Database\Seeders;

use App\Models\CaseTimelineEvent;
use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class CaseTimelineEventSeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedModelFromJson(CaseTimelineEvent::class, 'bankruptcy_case_timeline_events.json');
    }
}
