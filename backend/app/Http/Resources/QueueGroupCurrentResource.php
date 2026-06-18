<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\QueueGroupCurrentData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One queue group's public board row: now-serving number + waiting count (task 011).
 *
 * @mixin QueueGroupCurrentData
 */
class QueueGroupCurrentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var QueueGroupCurrentData $data */
        $data = $this->resource;

        return [
            'id' => $data->queueGroup->id,
            'name' => $data->queueGroup->name,
            'prefix' => $data->queueGroup->prefix,
            'current_number' => $data->currentNumber,
            'waiting_count' => $data->waitingCount,
        ];
    }
}
