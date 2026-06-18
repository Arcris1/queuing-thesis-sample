<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\QueueGroup;
use App\Services\QueueService;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A queue group's line changed (serve / skip / join / leave / standby /
 * reclaim) — positions behind the change shifted (task 019, plan §12).
 *
 * Deliberately lightweight: it carries only the group id, the now-serving
 * number, and the waiting count so the board can refresh its summary. Clients
 * that need full per-ticket detail refetch GET /api/queue/current or
 * /api/queue/status — we never fan out every position on every change.
 *
 * Queued (ShouldBroadcast) — a board summary tolerates a few ms of latency,
 * unlike {@see TicketCalled}.
 *
 * Channels: private queue-group.{id} and office.{officeId} (staff boards).
 * broadcastAs: "queue.updated".
 */
final class QueueUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $queueGroupId,
        public readonly int $officeId,
        public readonly ?string $nowServing,
        public readonly int $waitingCount,
    ) {}

    /**
     * Build the event for a group, computing the now-serving number and waiting
     * count via {@see QueueService} so the summary logic lives in one place.
     * Call from the post-commit hook points in the routing/queue/presence flows.
     */
    public static function forGroup(QueueGroup $queueGroup): self
    {
        /** @var QueueService $queue */
        $queue = app(QueueService::class);

        return new self(
            queueGroupId: $queueGroup->id,
            officeId: $queueGroup->office_id,
            nowServing: $queue->currentNumberFor($queueGroup),
            waitingCount: $queue->waitingCountFor($queueGroup),
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
        return 'queue.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'queue_group_id' => $this->queueGroupId,
            'office_id' => $this->officeId,
            'now_serving' => $this->nowServing,
            'waiting_count' => $this->waitingCount,
        ];
    }
}
