<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\ServiceHistorySeeder;
use Illuminate\Console\Command;

/**
 * `php artisan ml:seed-history {count?}` — fabricate synthetic `service_history`
 * to bootstrap the wait-time model (task 022, plan §10). Real history is thin
 * until the system has run for months; this gives the regression something to
 * learn from for the defense. Volume defaults to ~1500 rows.
 *
 * The generated data is CLEARLY SYNTHETIC (see {@see ServiceHistorySeeder}).
 */
final class SeedServiceHistory extends Command
{
    protected $signature = 'ml:seed-history {count=1500 : Number of synthetic rows to generate}';

    protected $description = 'Generate synthetic service_history rows to bootstrap the wait-time model (plan §10).';

    public function handle(ServiceHistorySeeder $seeder): int
    {
        $count = (int) $this->argument('count');

        if ($count < 1) {
            $this->error('count must be a positive integer.');

            return self::FAILURE;
        }

        $seeder->setCommand($this);
        $this->info("Generating {$count} synthetic service_history rows…");
        $seeder->run($count);

        return self::SUCCESS;
    }
}
