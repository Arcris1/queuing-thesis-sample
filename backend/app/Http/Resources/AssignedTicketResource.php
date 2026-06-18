<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Office;
use App\Models\QueueGroup;
use App\Models\QueueTicket;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The staff-facing shape of a ticket a window is (or was) serving — returned by
 * the window available/serve/skip/recall endpoints (task 021). Unlike
 * {@see QueueTicketResource} this wraps a {@see QueueTicket} directly: staff need
 * the student identity and call/serve timestamps, not the student's live queue
 * position.
 *
 * @mixin QueueTicket
 */
class AssignedTicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var QueueTicket $ticket */
        $ticket = $this->resource;

        /** @var QueueGroup $queueGroup */
        $queueGroup = $ticket->queueGroup;
        /** @var Office $office */
        $office = $queueGroup->office;
        /** @var Service $service */
        $service = $ticket->service;
        /** @var User $user */
        $user = $ticket->user;

        return [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'status' => $ticket->status->value,
            'status_label' => $ticket->status->label(),
            'priority' => $ticket->priority,
            'joined_at' => $ticket->joined_at?->toISOString(),
            'called_at' => $ticket->called_at?->toISOString(),
            'served_at' => $ticket->served_at?->toISOString(),
            'student' => [
                'id' => $user->id,
                'name' => $user->name,
                'student_no' => $user->student_no,
            ],
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
