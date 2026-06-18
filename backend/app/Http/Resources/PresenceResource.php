<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\PresenceResultData;
use App\Models\Office;
use App\Models\QueueGroup;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The outcome of a heartbeat (task 015, plan §9): the server-derived presence
 * status, the recorded last_seen, and a thin view of the ticket the ping was
 * bound to so the app can confirm which ticket it is keeping alive.
 *
 * @mixin PresenceResultData
 */
class PresenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PresenceResultData $data */
        $data = $this->resource;

        $presence = $data->presenceStatus;
        $ticket = $data->ticket;

        /** @var QueueGroup $queueGroup */
        $queueGroup = $ticket->queueGroup;
        /** @var Office $office */
        $office = $queueGroup->office;
        /** @var Service $service */
        $service = $ticket->service;

        return [
            'presence_status' => $presence->value,
            'presence_label' => $presence->label(),
            'presence_color' => $presence->color(),
            'last_seen' => $data->heartbeat->last_seen->toISOString(),
            'ticket' => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'status' => $ticket->status->value,
                'status_label' => $ticket->status->label(),
                'office' => [
                    'id' => $office->id,
                    'name' => $office->name,
                ],
                'queue_group' => [
                    'id' => $queueGroup->id,
                    'name' => $queueGroup->name,
                    'prefix' => $queueGroup->prefix,
                ],
                'service' => [
                    'id' => $service->id,
                    'name' => $service->name,
                ],
            ],
        ];
    }
}
