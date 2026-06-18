<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\TicketStatusData;
use App\Models\Office;
use App\Models\QueueGroup;
use App\Models\QueueTicket;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes a single ticket with its live position within its queue group. Wraps a
 * {@see TicketStatusData} so the position/people_ahead are computed in the
 * service, not here (tasks 008/010).
 *
 * @mixin TicketStatusData
 */
class QueueTicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TicketStatusData $data */
        $data = $this->resource;
        $ticket = $data->ticket;

        /** @var QueueGroup $queueGroup */
        $queueGroup = $ticket->queueGroup;
        /** @var Office $office */
        $office = $queueGroup->office;
        /** @var Service $service */
        $service = $ticket->service;

        return [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'status' => $ticket->status->value,
            'status_label' => $ticket->status->label(),
            'priority' => $ticket->priority,
            'position' => $data->position,
            'people_ahead' => $data->peopleAhead,
            'current_number' => $data->currentNumber,
            'eta' => $data->eta === null ? null : [
                'estimated_minutes' => $data->eta->estimatedMinutes,
                'confidence' => $data->eta->confidence,
                'active_windows' => $data->eta->activeWindows,
                'basis' => $data->eta->basis->value,
                'model_version' => $data->eta->modelVersion,
                'trained_at' => $data->eta->trainedAt,
            ],
            'joined_at' => $ticket->joined_at?->toISOString(),
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
        ];
    }
}
