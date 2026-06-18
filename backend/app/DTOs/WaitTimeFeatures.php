<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * The model-input context for a single wait-time prediction (plan §10). These
 * are the live features the {@see \App\Services\WaitTimePredictor} encodes into
 * the regression design vector to predict a SERVICE duration (minutes) for the
 * given context. Built either from a serving ticket (live estimate) or from a
 * historical row (training).
 *
 * Note: `peopleAhead` and `activeWindows` are NOT model inputs to the per-row
 * service-duration regression — they scale the final estimate
 * `(peopleAhead × predictedServiceMinutes) ÷ max(activeWindows, 1)`. They live
 * here so the predictor has the full context in one object.
 */
final readonly class WaitTimeFeatures
{
    public function __construct(
        public int $officeId,
        public int $queueGroupId,
        public int $serviceId,
        public float $avgServiceMinutes,
        public int $hourOfDay,
        public int $dayOfWeek,
        public int $activeWindows,
        public int $peopleAhead = 0,
    ) {}
}
