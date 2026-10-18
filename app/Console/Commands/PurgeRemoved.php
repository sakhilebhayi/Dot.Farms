<?php

namespace App\Console\Commands;

use App\Services\Farms\RemovedPurger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PurgeRemoved extends Command
{
    protected $signature = 'farms:purge-removed {--dry-run : Report what would be deleted without deleting anything}';

    protected $description = 'Permanently delete farms, fields, crops, crop cycles and records removed more than REMOVED_RETENTION_DAYS ago';

    private const NAMES = [
        'farms' => 'farm',
        'fields' => 'field',
        'crops' => 'crop',
        'crop_cycles' => 'crop cycle',
        'planting_records' => 'planting record',
        'harvest_records' => 'harvest record',
    ];

    public function handle(RemovedPurger $purger): int
    {
        $days = (int) config('farms.removed_retention_days');
        if ($days < 1) {
            $this->error('REMOVED_RETENTION_DAYS must be at least 1; refusing to purge.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days);
        $purged = $purger->purge($cutoff, $dryRun);

        Log::info('farms.purge-removed', [
            'dry_run' => $dryRun,
            'retention_days' => $days,
            'cutoff' => $cutoff->toIso8601String(),
            'purged' => $purged,
        ]);

        $parts = [];
        foreach ($purged as $table => $count) {
            if ($count > 0) {
                $parts[] = $count.' '.Str::plural(self::NAMES[$table], $count);
            }
        }

        $this->line(match (true) {
            $parts === [] => "Nothing to purge: no record has been removed for more than {$days} days.",
            $dryRun => 'Dry run, nothing deleted. Would purge '.implode(', ', $parts)." (removed before {$cutoff->toDateTimeString()}).",
            default => 'Purged '.implode(', ', $parts)." (removed before {$cutoff->toDateTimeString()}).",
        });

        return self::SUCCESS;
    }
}
