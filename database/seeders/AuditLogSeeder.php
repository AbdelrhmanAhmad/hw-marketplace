<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsFromJson;
use Illuminate\Database\Seeder;

class AuditLogSeeder extends Seeder
{
    use SeedsFromJson;

    public function run(): void
    {
        $this->seedTableFromJson('audit_logs', 'audit_logs.json');
    }
}
