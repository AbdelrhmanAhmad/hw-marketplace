<?php

namespace App\Console\Commands;

use App\Repositories\DatabaseMarketplaceRepository;
use App\Repositories\StaticPlatformAppsRepository;
use Illuminate\Console\Command;

/**
 * يقارن StaticPlatformAppsRepository (Legacy) مقابل DatabaseMarketplaceRepository
 * (الجديد) حقلًا حقلًا لكل عنصر — شرط إلزامي قبل أي Cutover (Blueprint قسم D، BR-011).
 */
class MarketplaceCatalogParityCheck extends Command
{
    protected $signature = 'marketplace:catalog-parity-check';

    protected $description = 'يقارن كتالوج PlatformApps القديم مقابل marketplace_items الجديد حقلًا حقلًا';

    /** الحقول التي تُقارَن حرفيًا بين المصدرين. */
    private const FIELDS = ['key', 'name', 'tagline', 'description', 'status', 'icon'];

    public function handle(): int
    {
        $static = (new StaticPlatformAppsRepository)->all()->keyBy('key');
        $database = (new DatabaseMarketplaceRepository)->all()->keyBy('key');

        $allKeys = $static->keys()->merge($database->keys())->unique()->sort()->values();

        $mismatches = [];
        $fieldCounts = array_fill_keys(self::FIELDS, 0);
        $freeMatches = 0;
        $audienceMatches = 0;

        foreach ($allKeys as $key) {
            $old = $static->get($key);
            $new = $database->get($key);

            if (! $old || ! $new) {
                $mismatches[] = "[{$key}] غير موجود في " . (! $old ? 'المصدر القديم' : 'المصدر الجديد');

                continue;
            }

            foreach (self::FIELDS as $field) {
                if (($old[$field] ?? null) === ($new[$field] ?? null)) {
                    $fieldCounts[$field]++;
                } else {
                    $mismatches[] = "[{$key}] حقل '{$field}': قديم='" . ($old[$field] ?? 'null') . "' ≠ جديد='" . ($new[$field] ?? 'null') . "'";
                }
            }

            $oldFree = $old['free'] ?? false;
            $newFree = $new['free'] ?? false;
            if ($oldFree === $newFree) {
                $freeMatches++;
            } else {
                $mismatches[] = "[{$key}] حقل 'free': قديم='" . var_export($oldFree, true) . "' ≠ جديد='" . var_export($newFree, true) . "'";
            }

            $oldAudiences = collect($old['audiences'] ?? [])->sort()->values()->all();
            $newAudiences = collect($new['audiences'] ?? [])->sort()->values()->all();
            if ($oldAudiences === $newAudiences) {
                $audienceMatches++;
            } else {
                $mismatches[] = "[{$key}] حقل 'audiences': قديم=" . json_encode($oldAudiences) . " ≠ جديد=" . json_encode($newAudiences);
            }
        }

        $total = $allKeys->count();

        $this->info('نتيجة Parity Check:');
        $this->line("Items:        {$database->count()}/{$static->count()} (قديم/جديد بنفس العدد الإجمالي المتوقَّع {$total})");
        foreach (self::FIELDS as $field) {
            $label = match ($field) {
                'key' => 'Slugs/Keys',
                'name' => 'Names',
                'tagline' => 'Taglines',
                'description' => 'Descriptions',
                'status' => 'Status',
                'icon' => 'Icons',
                default => $field,
            };
            $this->line(str_pad($label, 14) . ": {$fieldCounts[$field]}/{$total}");
        }
        $this->line(str_pad('Free/Pricing', 14) . ": {$freeMatches}/{$total}");
        $this->line(str_pad('Audiences', 14) . ": {$audienceMatches}/{$total}");
        $this->line(str_pad('Categories', 14) . ': N/A — لا مفهوم تصنيف بالمصدر القديم أصلًا (الاثنان بلا تصنيف، لا مقارنة ممكنة أو مطلوبة)');

        if (empty($mismatches)) {
            $this->newLine();
            $this->info('✅ تطابق كامل 100% — لا فروقات.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->error('❌ فروقات موجودة:');
        foreach ($mismatches as $mismatch) {
            $this->line("  - {$mismatch}");
        }

        return self::FAILURE;
    }
}
