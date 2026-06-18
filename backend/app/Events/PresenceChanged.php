<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\PresenceStatus;
use App\Models\QueueTicket;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A ticket's presence transitioned to a board-relevant state (Standby on grace
 * lapse, or reclaimed → Removed) — lets the staff dashboard repaint a row
 * without a full refetch (task 019, optional, plan §9/§12).
 *
 * Queued (ShouldBroadcast). Channels: queue-group.{id} + office.{officeId}
 * (staff boards only — students don't watch other students' presence).
 * broadcastAs: "presence.changed".
 */
final class PresenceChanged implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $ticketId,
        public readonly int $queueGroupId,
        public readonly int $officeId,
        public readonly PresenceStatus $presence,
        public readonly string $ticketStatus,
    ) {}

    /**
     * Build the event from a ticket whose group/office are loaded (or loadable).
     */
    public static function forTicket(QueueTicket $ticket, PresenceStatus $presence): self
    {
        $ticket->loadMissing('queueGroup');

        /** @var \App\Models\QueueGroup $group */
        $group = $ticket->queueGroup;

        return new self(
            ticketId: $ticket->id,
            queueGroupId: $group->id,
            officeId: $group->office_id,
            presence: $presence,
            ticketStatus: $ticket->status->value,
        );
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("queue-group.{$this->queueGroupId}"),
            new PrivateChannel("office.{$this->officeId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'presence.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'ticket_id' => $this->ticketId,
            'queue_group_id' => $this->queueGroupId,
            'office_id' => $this->officeId,
            'presence' => $this->presence->value,
            'ticket_status' => $this->ticketStatus,
        ];
    }
}
