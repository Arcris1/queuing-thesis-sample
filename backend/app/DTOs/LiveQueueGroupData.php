<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\QueueGroup;

/**
 * One queue group's live line for the board (task 025): its now-serving number,
 * the waiting/active/away/offline/standby counts, and the ordered list of waiting
 * tickets each with live position, presence, and ETA. Counts are aggregated in
 * SQL; the per-ticket list is bounded and ordered by the routing rule.
 *
 * @param  array<int, LiveTicketData>  $tickets
 */
final readonly class LiveQueueGroupData
{
    /**
     * @param  array<int, LiveTicketData>  $tickets
     * @param  array<string, int>  $counts  keyed waiting/active/away/offline/standby
     */
    public function __construct(
        public QueueGroup $queueGroup,
        public ?string $nowServing,
        public int $waitingCount,
        public array $counts,
        public array $tickets,
    ) {}
}
