<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\PresenceStatus;
use App\Models\QueueTicket;
use App\Services\WaitTimePredictor;

/**
 * A single waiting/ready ticket as it appears on the live board (task 025):
 * ticket number, status, priority, the student's derived presence (from the
 * latest heartbeat), live position within the group, and the AI wait-time ETA.
 * The ETA flows through the shared {@see WaitTimePredictor} so the
 * board agrees with /queue/status.
 */
final readonly class LiveTicketData
{
    public function __construct(
        public QueueTicket $ticket,
        public PresenceStatus $presence,
        public int $position,
        public ?WaitTimePrediction $eta,
    ) {}
}
