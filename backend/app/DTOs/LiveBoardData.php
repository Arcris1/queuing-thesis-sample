<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Events\QueueUpdated;
use App\Events\TicketCalled;
use App\Models\Office;

/**
 * The realtime live-board snapshot for one office (task 025, plan §5 / §7 / §12):
 * the office, every queue group's line (now-serving + counts + the ordered waiting
 * tickets), and every window's operating state. Consumed by the Vue live board
 * (task 036); clients fetch this once then live-update via the Reverb broadcasts
 * ({@see QueueUpdated} / {@see TicketCalled}).
 *
 * @param  array<int, LiveQueueGroupData>  $queueGroups
 * @param  array<int, LiveWindowData>  $windows
 */
final readonly class LiveBoardData
{
    /**
     * @param  array<int, LiveQueueGroupData>  $queueGroups
     * @param  array<int, LiveWindowData>  $windows
     */
    public function __construct(
        public Office $office,
        public array $queueGroups,
        public array $windows,
    ) {}
}
