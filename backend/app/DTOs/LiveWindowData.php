<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\QueueTicket;
use App\Models\Window;
use Illuminate\Support\Carbon;

/**
 * One window's operating state for the live board (task 025): its status, the
 * queue groups it currently serves (the live `window_queue_groups` pivot the
 * routing engine reads), and its current open assignment — the ticket it is
 * serving and since when. `currentTicket` is null when the window is idle/closed.
 */
final readonly class LiveWindowData
{
    public function __construct(
        public Window $window,
        public ?QueueTicket $currentTicket,
        public ?Carbon $assignedAt,
    ) {}
}
