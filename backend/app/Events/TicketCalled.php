<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Office;
use App\Models\QueueGroup;
use App\Models\QueueTicket;
use App\Models\Window;
use App\Models\WindowAssignment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A window has been assigned (or re-announced for) a ticket — "you're being
 * called to Window X" (task 019, plan §12). Implements ShouldBroadcastNow:
 * being called is latency-sensitive, so it skips the queue and broadcasts
 * synchronously.
 *
 * Broadcast on three channels (see broadcastOn):
 *   - private  user.{studentId}        → the student's personal "proceed" event
 *   - private  queue-group.{groupId}   → that group's staff board
 *   - private  office.{officeId}       → the office-wide staff board
 *
 * broadcastAs: "ticket.called". Payload: {@see broadcastWith()}.
 */
final class TicketCalled implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly WindowAssignment $assignment,
    ) {
        // Ensure the relations the payload + channels need are present without
        // re-querying per accessor (no N+1 when dispatched from the services,
        // which already eager-load these).
        $this->assignment->loadMissing([
            'window.office',
            'ticket.queueGroup.office',
            'ticket.service',
            'ticket.user',
        ]);
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $ticket = $this->ticket();
        $group = $ticket->queueGroup;

        return [
            new PrivateChannel("user.{$ticket->user_id}"),
            new PrivateChannel("queue-group.{$group->id}"),
            new PrivateChannel("office.{$group->office_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ticket.called';
    }

    /**
     * DTO-shaped payload (CLAUDE.md): the called ticket, the window it must
     * proceed to, and the queue group, plus a ready-to-show proceed message.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $ticket = $this->ticket();
        /** @var Window $window */
        $window = $this->assignment->window;
        /** @var QueueGroup $group */
        $group = $ticket->queueGroup;
        /** @var Office $office */
        $office = $group->office;

        return [
            'ticket' => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'status' => $ticket->status->value,
                'student_id' => $ticket->user_id,
                'called_at' => $ticket->called_at?->toISOString(),
            ],
            'window' => [
                'id' => $window->id,
                'name' => $window->name,
            ],
            'queue_group' => [
                'id' => $group->id,
                'name' => $group->name,
                'prefix' => $group->prefix,
            ],
            'office' => [
                'id' => $office->id,
                'name' => $office->name,
            ],
            'message' => sprintf(
                'Please proceed to %s — %s. You are being called (ticket %s).',
                $office->name,
                $window->name,
                $ticket->ticket_number,
            ),
        ];
    }

    private function ticket(): QueueTicket
    {
        /** @var QueueTicket $ticket */
        $ticket = $this->assignment->ticket;

        return $ticket;
    }
}
