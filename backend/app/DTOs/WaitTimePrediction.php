<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\PredictionBasis;

/**
 * The result of a wait-time estimate (task 024). Carries the final
 * window-aware estimate plus provenance so the API/dashboard can show whether
 * the AI model or the naive fallback produced it.
 *
 *   estimatedMinutes        — (peopleAhead × predictedServiceMinutes) ÷ windows
 *   predictedServiceMinutes — the per-context service duration the basis produced
 *   confidence              — [0,1], derived from model R² (or the fallback const)
 *   basis                   — model vs fallback
 *   trainedAt / modelVersion — provenance of the model used (null for fallback)
 */
final readonly class WaitTimePrediction
{
    public function __construct(
        public int $estimatedMinutes,
        public float $predictedServiceMinutes,
        public float $confidence,
        public PredictionBasis $basis,
        public int $peopleAhead,
        public int $activeWindows,
        public ?string $modelVersion = null,
        public ?string $trainedAt = null,
    ) {}
}
