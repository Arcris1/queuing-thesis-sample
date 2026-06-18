<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\QueueGroup;

/**
 * A queue group's public board figures: its latest now-serving number and the
 * number of people still waiting in that group today (task 011).
 */
final readonly class QueueGroupCurrentData
{
    public function __construct(
        public QueueGroup $queueGroup,
        public ?string $currentNumber,
        public int $waitingCount,
    ) {}
}
