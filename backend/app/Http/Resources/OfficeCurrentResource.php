<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\OfficeCurrentData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An office and its queue groups for the public "now serving" board (task 011).
 *
 * @mixin OfficeCurrentData
 */
class OfficeCurrentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var OfficeCurrentData $data */
        $data = $this->resource;

        return [
            'office' => [
                'id' => $data->office->id,
                'name' => $data->office->name,
            ],
            'queue_groups' => QueueGroupCurrentResource::collection($data->queueGroups),
        ];
    }
}
