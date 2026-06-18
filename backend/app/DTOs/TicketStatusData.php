<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\QueueTicket;

/**
 * A ticket together with its live, computed queue position and AI wait-time ETA. Computing these
 * server-side keeps the QueueTicketResource free of query logic (tasks 008/010).
 */
final readonly class TicketStatusData
{
    public function __construct(
        public QueueTicket $ticket,
        public int $position,
        public int $peopleAhead,
        public ?string $currentNumber = null,
        public ?WaitTimePrediction $eta = null,
    ) {}
}
