<?php

namespace App\Console\Commands;

use App\Models\Activity;
use Illuminate\Console\Command;

class PruneActivityLog extends Command
{
    protected $signature = 'activity:prune {--months=12 : Keep this many months of history}';

    protected $description = 'Delete activity log rows older than the retention window';

    public function handle(): int
    {
        $months = max(1, (int) $this->option('months'));
        $cutoff = now()->subMonths($months);

        $deleted = Activity::query()->where('created_at', '<', $cutoff)->delete();

        $this->info("Pruned {$deleted} activity rows older than {$months} months.");

        return self::SUCCESS;
    }
}
