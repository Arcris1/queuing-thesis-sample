<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\Office;

/**
 * An office with the public board figures for each of its queue groups (task 011).
 */
final readonly class OfficeCurrentData
{
    /**
     * @param  array<int, QueueGroupCurrentData>  $queueGroups
     */
    public function __construct(
        public Office $office,
        public array $queueGroups,
    ) {}
}
