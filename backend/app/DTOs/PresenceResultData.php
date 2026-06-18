<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\PresenceStatus;
use App\Models\Heartbeat;
use App\Models\QueueTicket;

/**
 * Server-derived outcome of a heartbeat (task 015, plan §9): the just-recorded
 * heartbeat, the live presence status derived from its age against the config
 * thresholds, and the ticket the ping was bound to.
 */
final readonly class PresenceResultData
{
    public function __construct(
        public Heartbeat $heartbeat,
        public PresenceStatus $presenceStatus,
        public QueueTicket $ticket,
    ) {}
}
