<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\QueueGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QueueGroup
 */
class QueueGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'prefix' => $this->prefix,
            'services' => ServiceResource::collection($this->whenLoaded('services')),
        ];
    }
}
