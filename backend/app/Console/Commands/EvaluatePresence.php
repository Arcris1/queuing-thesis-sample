<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PresenceService;
use Illuminate\Console\Command;

/**
 * Scheduled presence re-evaluation (task 016, plan §9). Runs every minute off the
 * scheduler (registered in routes/console.php): scans today's still-in-line
 * tickets and reclaims any whose presence has decayed to Removed (heartbeat older
 * than the configured removed threshold), freeing their slot.
 *
 * Idempotent — re-running changes nothing if no ticket has crossed the threshold.
 */
final class EvaluatePresence extends Command
{
    protected $signature = 'presence:evaluate';

    protected $description = 'Re-evaluate ticket presence and reclaim abandoned (Removed) tickets (plan §9).';

    public function handle(PresenceService $presence): int
    {
        $reclaimed = $presence->reclaimAbandoned();

        $this->info("Presence evaluated. Reclaimed {$reclaimed} abandoned ticket(s).");

        return self::SUCCESS;
    }
}
