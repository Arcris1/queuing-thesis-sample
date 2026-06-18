<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whether a wait-time estimate came from the trained regression model or from
 * the naive window-aware fallback (plan §10, task 024). Surfaced to clients as
 * `basis` so the dashboard/app can show whether AI or the fallback was used.
 */
enum PredictionBasis: string
{
    case Model = 'model';
    case Fallback = 'fallback';

    public function label(): string
    {
        return match ($this) {
            self::Model => 'AI prediction',
            self::Fallback => 'Estimate',
        };
    }
}
