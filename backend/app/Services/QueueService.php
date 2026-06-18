<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\JoinQueueData;
use App\DTOs\OfficeCurrentData;
use App\DTOs\QueueGroupCurrentData;
use App\DTOs\TicketStatusData;
use App\DTOs\WaitTimePrediction;
use App\Enums\QueueStatus;
use App\Enums\TicketStatus;
use App\Events\QueueUpdated;
use App\Exceptions\AlreadyInQueueException;
use App\Exceptions\NotInQueueException;
use App\Models\Office;
use App\Models\Queue;
use App\Models\QueueGroup;
use App\Models\QueueTicket;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class QueueService
{
    public function __construct(
        private readonly WaitTimePredictor $predictor,
    ) {}

    /**
     * Join the queue group of the chosen service. The office and queue group are
     * derived server-side from the service — the client never sends them (task 008).
     *
     * @throws AlreadyInQueueException when the user already holds an active ticket
     *                                 in that queue group today
     */
    public function join(User $user, JoinQueueData $data): TicketStatusData
    {
        /** @var Service $service */
        $service = Service::query()
            ->with('queueGroup.office')
            ->findOrFail($data->serviceId);

        /** @var QueueGroup $queueGroup */
        $queueGroup = $service->queueGroup;

        $ticket = DB::transaction(function () use ($user, $service, $queueGroup): QueueTicket {
            // One active ticket per user per queue group per day.
            $hasActive = QueueTicket::query()
                ->where('user_id', $user->id)
                ->where('queue_group_id', $queueGroup->id)
                ->whereIn('status', TicketStatus::active())
                ->forToday()
                ->lockForUpdate()
                ->exists();

            if ($hasActive) {
                throw new AlreadyInQueueException();
            }

            $queue = $this->todaysQueue($queueGroup->office);
            $ticketNumber = $this->nextTicketNumber($queueGroup);

            return QueueTicket::create([
                'queue_id' => $queue->id,
                'queue_group_id' => $queueGroup->id,
                'service_id' => $service->id,
                'user_id' => $user->id,
                'ticket_number' => $ticketNumber,
                'status' => TicketStatus::Waiting,
                'priority' => 0,
                'joined_at' => now(),
            ]);
        });

        $ticket->setRelation('queueGroup', $queueGroup);
        $ticket->setRelation('service', $service);

        // After commit (task 019): a new ticket joined the group → refresh boards.
        event(QueueUpdated::forGroup($queueGroup));

        return $this->statusForTicket($ticket);
    }

    /**
     * Mark the user's current active ticket as left (Skipped) without touching
     * called_at/served_at. Tickets are never hard-deleted (history/analytics).
     *
     * @throws NotInQueueException when the user has no active ticket
     */
    public function leave(User $user): QueueTicket
    {
        $ticket = DB::transaction(function () use ($user): QueueTicket {
            $ticket = $this->activeTicketFor($user, lock: true);

            if ($ticket === null) {
                throw new NotInQueueException();
            }

            $ticket->update(['status' => TicketStatus::Skipped]);

            return $ticket;
        });

        // After commit (task 019): the student left the line → refresh boards.
        $ticket->loadMissing('queueGroup');

        /** @var QueueGroup $group */
        $group = $ticket->queueGroup;

        event(QueueUpdated::forGroup($group));

        return $ticket;
    }

    /**
     * The authenticated student's current ticket with live position/people-ahead,
     * or null when they hold no active ticket (task 010).
     */
    public function statusFor(User $user): ?TicketStatusData
    {
        $ticket = $this->activeTicketFor($user);

        if ($ticket === null) {
            return null;
        }

        $ticket->load(['queueGroup.office', 'service']);

        return $this->statusForTicket($ticket);
    }

    /**
     * AI-predicted wait time for the user's active ticket (task 024). Returns null
     * when they hold no active ticket (the controller turns that into a 404). The
     * people-ahead-in-group count is computed exactly as the status endpoint does,
     * then the shared {@see WaitTimePredictor} produces the window-aware estimate.
     */
    public function estimateFor(User $user): ?WaitTimePrediction
    {
        $ticket = $this->activeTicketFor($user);

        if ($ticket === null) {
            return null;
        }

        $ticket->load(['queueGroup.office', 'service']);

        $peopleAhead = $this->peopleAheadFor($ticket);

        return $this->predictor->predictForTicket($ticket, $peopleAhead);
    }

    /**
     * Public board data: today's queue groups (optionally scoped to one office)
     * each with their now-serving number and waiting count (task 011).
     *
     * @return array<int, OfficeCurrentData>
     */
    public function current(?int $officeId = null): array
    {
        /** @var Collection<int, Office> $offices */
        $offices = Office::query()
            ->when($officeId !== null, fn ($query) => $query->whereKey($officeId))
            ->with(['queueGroups' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();

        return $offices
            ->map(fn (Office $office): OfficeCurrentData => new OfficeCurrentData(
                office: $office,
                queueGroups: $office->queueGroups
                    ->map(fn (QueueGroup $group): QueueGroupCurrentData => new QueueGroupCurrentData(
                        queueGroup: $group,
                        currentNumber: $this->currentNumberFor($group),
                        waitingCount: $this->waitingCountFor($group),
                    ))
                    ->all(),
            ))
            ->all();
    }

    /**
     * The most recent serving number in a queue group today, e.g. "A-014".
     */
    public function currentNumberFor(QueueGroup $queueGroup): ?string
    {
        /** @var QueueTicket|null $serving */
        $serving = QueueTicket::query()
            ->where('queue_group_id', $queueGroup->id)
            ->where('status', TicketStatus::Serving)
            ->forToday()
            ->orderByDesc('called_at')
            ->orderByDesc('id')
            ->first();

        return $serving?->ticket_number;
    }

    /**
     * Count of waiting/ready tickets in a queue group today (task 011).
     */
    public function waitingCountFor(QueueGroup $queueGroup): int
    {
        return QueueTicket::query()
            ->where('queue_group_id', $queueGroup->id)
            ->whereIn('status', [TicketStatus::Waiting, TicketStatus::Ready])
            ->forToday()
            ->count();
    }

    /**
     * Today's open daily-session row for an office, created on first join.
     */
    private function todaysQueue(Office $office): Queue
    {
        /** @var Queue $queue */
        $queue = Queue::query()->firstOrCreate(
            ['office_id' => $office->id, 'date' => today()],
            ['status' => QueueStatus::Open],
        );

        return $queue;
    }

    /**
     * Next group-prefixed, zero-padded ticket number for today. Must run inside a
     * transaction with the group's tickets locked to avoid duplicates under
     * concurrency — call after a lockForUpdate read on the group (task 008).
     */
    private function nextTicketNumber(QueueGroup $queueGroup): string
    {
        $issuedToday = QueueTicket::query()
            ->where('queue_group_id', $queueGroup->id)
            ->forToday()
            ->lockForUpdate()
            ->count();

        $sequence = $issuedToday + 1;

        return sprintf('%s-%03d', $queueGroup->prefix, $sequence);
    }

    /**
     * The user's current active (Waiting/Ready/Serving/Standby) ticket today, if any.
     */
    private function activeTicketFor(User $user, bool $lock = false): ?QueueTicket
    {
        /** @var QueueTicket|null $ticket */
        $ticket = QueueTicket::query()
            ->where('user_id', $user->id)
            ->whereIn('status', TicketStatus::active())
            ->forToday()
            ->orderByDesc('joined_at')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->first();

        return $ticket;
    }

    /**
     * Build the live status (position + people-ahead) for a ticket, computed
     * within its queue group respecting the routing order (priority desc,
     * joined_at asc — matches scopeWaitingEligibleOldest, plan §5).
     */
    private function statusForTicket(QueueTicket $ticket): TicketStatusData
    {
        /** @var QueueGroup $queueGroup */
        $queueGroup = $ticket->queueGroup;

        $peopleAhead = $this->peopleAheadFor($ticket);

        // Populate the AI ETA via the shared predictor seam (task 024) — same code
        // path as /queue/estimate and the push ETA. Only meaningful while the
        // student is still in line; a serving/terminal ticket carries no ETA.
        $eta = $this->predictor->hasMeaningfulEta($ticket)
            ? $this->predictor->predictForTicket($ticket, $peopleAhead)
            : null;

        return new TicketStatusData(
            ticket: $ticket,
            position: $peopleAhead + 1,
            peopleAhead: $peopleAhead,
            currentNumber: $this->currentNumberFor($queueGroup),
            eta: $eta,
        );
    }

    /**
     * People strictly ahead of this ticket in its shared queue-group line: still
     * waiting, and ordered before it (higher priority, or same priority but joined
     * earlier). The single source of truth for people-ahead, shared by the status
     * and estimate paths (tasks 010/024).
     */
    public function peopleAheadFor(QueueTicket $ticket): int
    {
        return QueueTicket::query()
            ->where('queue_group_id', $ticket->queue_group_id)
            ->where('status', TicketStatus::Waiting)
            ->where('id', '!=', $ticket->id)
            ->where(function ($query) use ($ticket): void {
                $query
                    ->where('priority', '>', $ticket->priority)
                    ->orWhere(function ($inner) use ($ticket): void {
                        $inner
                            ->where('priority', $ticket->priority)
                            ->where('joined_at', '<', $ticket->joined_at);
                    });
            })
            ->forToday()
            ->count();
    }
}
