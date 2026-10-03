<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Backward-compatible alias for the marefa knowledge seeders.
 *
 * Prefer calling CategorySeeder / LawEntrySeeder / … directly, or DatabaseSeeder.
 */
class LawEntriesSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            LawEntrySeeder::class,
            LawArticleSeeder::class,
            CategoryLawEntrySeeder::class,
            LegalUpdateSeeder::class,
        ]);
    }
}
