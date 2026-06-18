<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\LiveBoardData;
use App\DTOs\LiveQueueGroupData;
use App\DTOs\LiveTicketData;
use App\DTOs\LiveWindowData;
use App\Models\Office;
use App\Models\QueueGroup;
use App\Models\QueueTicket;
use App\Models\Window;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes the live-board snapshot for one office (task 025): the office, its queue
 * groups (each with now-serving, counts, and the ordered waiting tickets with
 * presence + ETA), and its windows (status, served groups, current assignment).
 * Wraps a {@see LiveBoardData} so the computation stays in the service.
 *
 * @mixin LiveBoardData
 */
class LiveBoardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var LiveBoardData $data */
        $data = $this->resource;

        /** @var Office $office */
        $office = $data->office;

        return [
            'office' => [
                'id' => $office->id,
                'name' => $office->name,
            ],
            'queue_groups' => array_map(
                fn (LiveQueueGroupData $group): array => $this->queueGroup($group),
                $data->queueGroups,
            ),
            'windows' => array_map(
                fn (LiveWindowData $window): array => $this->window($window),
                $data->windows,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function queueGroup(LiveQueueGroupData $group): array
    {
        /** @var QueueGroup $model */
        $model = $group->queueGroup;

        return [
            'id' => $model->id,
            'name' => $model->name,
            'prefix' => $model->prefix,
            'now_serving' => $group->nowServing,
            'waiting_count' => $group->waitingCount,
            'counts' => $group->counts,
            'tickets' => array_map(
                fn (LiveTicketData $ticket): array => $this->ticket($ticket),
                $group->tickets,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ticket(LiveTicketData $data): array
    {
        /** @var QueueTicket $ticket */
        $ticket = $data->ticket;

        return [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'status' => $ticket->status->value,
            'status_label' => $ticket->status->label(),
            'priority' => $ticket->priority,
            'position' => $data->position,
            'presence_status' => $data->presence->value,
            'presence_label' => $data->presence->label(),
            'eta' => $data->eta === null ? null : [
                'estimated_minutes' => $data->eta->estimatedMinutes,
                'confidence' => $data->eta->confidence,
                'active_windows' => $data->eta->activeWindows,
                'basis' => $data->eta->basis->value,
            ],
            'joined_at' => $ticket->joined_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function window(LiveWindowData $data): array
    {
        /** @var Window $window */
        $window = $data->window;

        return [
            'id' => $window->id,
            'name' => $window->name,
            'status' => $window->status->value,
            'status_label' => $window->status->label(),
            'queue_groups' => $window->queueGroups
                ->map(fn (QueueGroup $group): array => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'prefix' => $group->prefix,
                ])
                ->values()
                ->all(),
            'current_assignment' => $data->currentTicket === null ? null : [
                'ticket_id' => $data->currentTicket->id,
                'ticket_number' => $data->currentTicket->ticket_number,
                'student' => $this->student($data->currentTicket),
                'since' => $data->assignedAt?->toISOString(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function student(QueueTicket $ticket): ?array
    {
        $user = $ticket->user;

        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
        ];
    }
}
