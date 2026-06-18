<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Office;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes the join-screen catalog: the office summary plus its open queue groups,
 * each with nested services. Expects `queueGroups.services` to be eager-loaded.
 *
 * @mixin Office
 */
class OfficeServicesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'office' => [
                'id' => $this->id,
                'name' => $this->name,
                'latitude' => (float) $this->latitude,
                'longitude' => (float) $this->longitude,
                'geofence_radius_m' => $this->geofence_radius_m,
            ],
            'queue_groups' => QueueGroupResource::collection($this->whenLoaded('queueGroups')),
        ];
    }
}
