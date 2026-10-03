<?php

namespace Database\Seeders\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait SeedsFromJson
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    protected function seedModelFromJson(string $modelClass, string $jsonFile, string $uniqueKey = 'id'): void
    {
        $rows = $this->loadSeedJson($jsonFile);

        if ($rows === []) {
            return;
        }

        $modelClass::withoutEvents(function () use ($modelClass, $rows, $uniqueKey): void {
            Model::unguarded(function () use ($modelClass, $rows, $uniqueKey): void {
                foreach ($rows as $row) {
                    $row = $this->normalizeJsonColumns($modelClass, $row);
                    $modelClass::query()->updateOrCreate(
                        [$uniqueKey => $row[$uniqueKey]],
                        $row,
                    );
                }
            });
        });

        $this->syncAutoIncrement($modelClass::query()->getModel()->getTable());
    }

    /**
     * Insert-only path for append-only tables (e.g. audit_logs) or pivots without models.
     */
    protected function seedTableFromJson(string $table, string $jsonFile, string $uniqueKey = 'id'): void
    {
        $rows = $this->loadSeedJson($jsonFile);

        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            foreach ($chunk as $row) {
                $exists = DB::table($table)->where($uniqueKey, $row[$uniqueKey])->exists();
                if ($exists) {
                    continue;
                }
                DB::table($table)->insert($row);
            }
        }

        $this->syncAutoIncrement($table);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function loadSeedJson(string $jsonFile): array
    {
        $path = database_path('seeders/data/'.$jsonFile);

        if (! is_file($path)) {
            throw new \RuntimeException("Seed data file missing: {$path}");
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new \RuntimeException("Invalid JSON seed data: {$path}");
        }

        return $decoded;
    }

    protected function syncAutoIncrement(string $table): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable($table)) {
            return;
        }

        $max = DB::table($table)->max('id');
        if ($max === null) {
            return;
        }

        $next = (int) $max + 1;
        DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = {$next}");
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function normalizeJsonColumns(string $modelClass, array $row): array
    {
        $model = new $modelClass;
        $casts = method_exists($model, 'getCasts') ? $model->getCasts() : [];

        foreach ($casts as $attribute => $cast) {
            if (! array_key_exists($attribute, $row)) {
                continue;
            }

            $castName = is_string($cast) ? $cast : '';
            if (! in_array($castName, ['array', 'json', 'object', 'collection'], true)) {
                continue;
            }

            if (is_string($row[$attribute]) && $row[$attribute] !== '') {
                $decoded = json_decode($row[$attribute], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $row[$attribute] = $decoded;
                }
            }
        }

        return $row;
    }
}

