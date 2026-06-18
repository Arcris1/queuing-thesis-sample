<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\AnalyticsResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes the aggregate analytics bundle (task 025, plan §12). Wraps an
 * {@see AnalyticsResult} whose every field was aggregated in SQL by the service —
 * this resource only names the response envelope.
 *
 * @mixin AnalyticsResult
 */
class AnalyticsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AnalyticsResult $result */
        $result = $this->resource;

        return [
            'avg_wait_minutes' => $result->avgWaitMinutes,
            'avg_service_minutes' => $result->avgServiceMinutes,
            'served' => $result->served,
            'missed' => $result->missed,
            'peak_hours' => $result->peakHours,
            'by_queue_group' => $result->byQueueGroup,
            'by_service' => $result->byService,
            'by_window' => $result->byWindow,
            'window_utilization' => $result->windowUtilization,
        ];
    }
}
