<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\LocationResultData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The server-decided geofence outcome of a location update (task 013). Shapes a
 * {@see LocationResultData} so the app can show "you are X m away (limit 15 m)"
 * and react to `within_range` — the single authoritative eligibility signal.
 *
 * @mixin LocationResultData
 */
class LocationResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var LocationResultData $data */
        $data = $this->resource;

        return [
            'distance_m' => $data->distanceMeters,
            'within_range' => $data->withinRange,
            'radius_m' => $data->radiusMeters,
            'recorded_at' => $data->log->recorded_at?->toISOString(),
            'office' => [
                'id' => $data->office->id,
                'name' => $data->office->name,
            ],
        ];
    }
}
