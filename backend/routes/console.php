<?php

declare(strict_types=1);

use App\Console\Commands\EvaluatePresence;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled presence re-evaluation (task 016, plan §9)
|--------------------------------------------------------------------------
| Runs the Active→Away→Offline→Removed scan every minute and reclaims tickets
| whose heartbeat is older than the removed threshold. withoutOverlapping keeps
| a slow run from stacking; runInBackground frees the scheduler tick.
*/
Schedule::command(EvaluatePresence::class)
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
