<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\QueueGroup;
use App\Models\QueueTicket;
use App\Models\Window;
use App\Models\WindowAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A window's operating state for the staff dashboard (task 021): the window
 * itself, the queue groups it currently serves (the live `window_queue_groups`
 * pivot that the routing engine reads), and its current open assignment, if any.
 *
 * @mixin Window
 */
class WindowStateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Window $window */
        $window = $this->resource;

        /** @var WindowAssignment|null $current */
        $current = $window->currentAssignment->first();

        return [
            'id' => $window->id,
            'name' => $window->name,
            'status' => $window->status->value,
            'status_label' => $window->status->label(),
            'office_id' => $window->office_id,
            'queue_groups' => $window->queueGroups
                ->map(fn (QueueGroup $group): array => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'prefix' => $group->prefix,
                ])
                ->values()
                ->all(),
            'current_ticket' => $current === null
                ? null
                : AssignedTicketResource::make($this->currentTicket($current))->resolve($request),
        ];
    }

    private function currentTicket(WindowAssignment $assignment): QueueTicket
    {
        /** @var QueueTicket $ticket */
        $ticket = $assignment->ticket;

        return $ticket;
    }
}
