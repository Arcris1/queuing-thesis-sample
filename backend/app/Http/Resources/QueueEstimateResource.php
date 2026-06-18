<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\WaitTimePrediction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The AI wait-time estimate for the caller's active ticket (task 024).
 *
 * Wraps a {@see WaitTimePrediction} in the standard `{ data: ... }` envelope:
 *   estimated_minutes — window-aware (people_ahead × predicted_service) ÷ windows
 *   confidence        — [0,1], from the model's holdout R² (or the fallback const)
 *   people_ahead      — people ahead in the queue group
 *   active_windows    — open windows serving the group (the divisor)
 *   basis             — 'model' when the trained model produced it, else 'fallback'
 *   model_version / trained_at — provenance (null on the fallback path)
 *
 * @mixin WaitTimePrediction
 */
class QueueEstimateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var WaitTimePrediction $prediction */
        $prediction = $this->resource;

        return [
            'estimated_minutes' => $prediction->estimatedMinutes,
            'predicted_service_minutes' => $prediction->predictedServiceMinutes,
            'confidence' => $prediction->confidence,
            'people_ahead' => $prediction->peopleAhead,
            'active_windows' => $prediction->activeWindows,
            'basis' => $prediction->basis->value,
            'model_version' => $prediction->modelVersion,
            'trained_at' => $prediction->trainedAt,
        ];
    }
}
