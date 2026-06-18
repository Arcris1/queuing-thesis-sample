<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Services\AnalyticsService;

/**
 * The computed analytics aggregates for a filter context (task 025, plan §12).
 * Every field is aggregated in SQL by {@see AnalyticsService}, not in
 * PHP loops. The `by*` arrays are lists of associative rows ready for the resource
 * to pass straight through.
 *
 * @param  array<int, array<string, int>>  $peakHours  rows: { hour, served }
 * @param  array<int, array<string, int|float|string|null>>  $byQueueGroup  avg duration per group
 * @param  array<int, array<string, int|float|string|null>>  $byService  avg duration per service
 * @param  array<int, array<string, int|float|string|null>>  $byWindow  served + avg duration per window
 * @param  array<int, array<string, int|float|string|null>>  $windowUtilization  busy/idle minutes per window
 */
final readonly class AnalyticsResult
{
    /**
     * @param  array<int, array<string, int>>  $peakHours
     * @param  array<int, array<string, int|float|string|null>>  $byQueueGroup
     * @param  array<int, array<string, int|float|string|null>>  $byService
     * @param  array<int, array<string, int|float|string|null>>  $byWindow
     * @param  array<int, array<string, int|float|string|null>>  $windowUtilization
     */
    public function __construct(
        public float $avgWaitMinutes,
        public float $avgServiceMinutes,
        public int $served,
        public int $missed,
        public array $peakHours,
        public array $byQueueGroup,
        public array $byService,
        public array $byWindow,
        public array $windowUtilization,
    ) {}
}
