<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\LocationLog;
use App\Models\Office;

/**
 * Server-computed outcome of a location update (task 013): the authoritative
 * distance/eligibility signal plus the office it was measured against.
 */
final readonly class LocationResultData
{
    public function __construct(
        public LocationLog $log,
        public Office $office,
        public float $distanceMeters,
        public bool $withinRange,
        public float $radiusMeters,
    ) {}
}
