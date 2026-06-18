<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\LiveBoardData;
use App\DTOs\LiveQueueGroupData;
use App\DTOs\LiveTicketData;
use App\DTOs\LiveWindowData;
use App\Enums\PresenceStatus;
use App\Enums\TicketStatus;
use App\Models\Office;
use App\Models\QueueGroup;
use App\Models\QueueTicket;
use App\Models\Window;
use App\Models\WindowAssignment;
use Illuminate\Database\Eloquent\Collection;

/**
 * Builds the realtime live-board snapshot for one office (task 025, plan §5 / §7 /
 * §12). The board is organized by queue group (lines) and by window (servers),
 * mirroring the service-level queueing model: students queue by service into a
 * shared group line, and windows are assigned across the groups they serve.
 *
 * Everything is assembled with eager loading + SQL aggregation so a poll-heavy
 * dashboard never triggers N+1: group counts come from a single grouped query,
 * the ordered waiting tickets are loaded once with their latest heartbeat, and the
 * windows are loaded once with their pivot + open assignment.
 */
final class LiveBoardService
{
    public function __construct(
        private readonly QueueService $queue,
        private readonly PresenceService $presence,
        private readonly WaitTimePredictor $predictor,
    ) {}

    /**
     * The full snapshot for an office: each queue group's line and each window's
     * state.
     */
    public function forOffice(Office $office): LiveBoardData
    {
        /** @var Collection<int, QueueGroup> $groups */
        $groups = $office->queueGroups()
            ->orderBy('name')
            ->get();

        $queueGroups = $groups
            ->map(fn (QueueGroup $group): LiveQueueGroupData => $this->buildGroup($group))
            ->all();

        $windows = $this->buildWindows($office);

        return new LiveBoardData(
            office: $office,
            queueGroups: $queueGroups,
            windows: $windows,
        );
    }

    /**
     * One queue group's line: now-serving number, the SQL-aggregated status counts,
     * presence counts derived from the loaded waiting/ready tickets' latest
     * heartbeats, and the ordered waiting-ticket rows (each with position + ETA).
     */
    private function buildGroup(QueueGroup $group): LiveQueueGroupData
    {
        // Status counts in one grouped query — no per-status round trips.
        /** @var array<string, int> $statusCounts */
        $statusCounts = QueueTicket::query()
            ->where('queue_group_id', $group->id)
            ->forToday()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        $waiting = (int) ($statusCounts[TicketStatus::Waiting->value] ?? 0)
            + (int) ($statusCounts[TicketStatus::Ready->value] ?? 0);
        $standby = (int) ($statusCounts[TicketStatus::Standby->value] ?? 0);

        // The ordered waiting/ready line (the routing order), loaded once with the
        // latest heartbeat so presence is derived without an extra query per row.
        /** @var Collection<int, QueueTicket> $lineTickets */
        $lineTickets = QueueTicket::query()
            ->where('queue_group_id', $group->id)
            ->whereIn('status', [TicketStatus::Ready, TicketStatus::Waiting])
            ->forToday()
            ->with('latestHeartbeat')
            ->orderByDesc('priority')
            ->orderByRaw('CASE status WHEN ? THEN 0 ELSE 1 END', [TicketStatus::Ready->value])
            ->orderBy('joined_at')
            ->get();

        // Presence counts over the in-line tickets (cheap — uses the heartbeat we
        // already eager-loaded; no extra queries).
        $presenceCounts = [
            PresenceStatus::Active->value => 0,
            PresenceStatus::Away->value => 0,
            PresenceStatus::Offline->value => 0,
        ];

        $tickets = [];
        $position = 0;

        foreach ($lineTickets as $ticket) {
            $ticket->setRelation('queueGroup', $group);

            $presence = $this->presence->evaluate($ticket);

            // Removed presence still shows as offline on the board (it has not been
            // reclaimed yet); bucket the eligible-ish states explicitly.
            $bucket = match ($presence) {
                PresenceStatus::Active => PresenceStatus::Active->value,
                PresenceStatus::Away => PresenceStatus::Away->value,
                default => PresenceStatus::Offline->value,
            };
            $presenceCounts[$bucket]++;

            $position++;

            $eta = $this->predictor->hasMeaningfulEta($ticket)
                ? $this->predictor->predictForTicket($ticket, $position - 1)
                : null;

            $tickets[] = new LiveTicketData(
                ticket: $ticket,
                presence: $presence,
                position: $position,
                eta: $eta,
            );
        }

        return new LiveQueueGroupData(
            queueGroup: $group,
            nowServing: $this->queue->currentNumberFor($group),
            waitingCount: $waiting,
            counts: [
                'waiting' => $waiting,
                'active' => $presenceCounts[PresenceStatus::Active->value],
                'away' => $presenceCounts[PresenceStatus::Away->value],
                'offline' => $presenceCounts[PresenceStatus::Offline->value],
                'standby' => $standby,
            ],
            tickets: $tickets,
        );
    }

    /**
     * Every window in the office with its served queue groups and current open
     * assignment, loaded in one pass (pivot + open assignment's ticket eager-loaded)
     * so the board renders without N+1.
     *
     * @return array<int, LiveWindowData>
     */
    private function buildWindows(Office $office): array
    {
        /** @var Collection<int, Window> $windows */
        $windows = $office->windows()
            ->with([
                'queueGroups' => fn ($query) => $query->orderBy('name'),
                'currentAssignment.ticket.service',
                'currentAssignment.ticket.user',
            ])
            ->orderBy('name')
            ->get();

        return $windows
            ->map(function (Window $window): LiveWindowData {
                /** @var WindowAssignment|null $current */
                $current = $window->currentAssignment->first();

                /** @var QueueTicket|null $ticket */
                $ticket = $current?->ticket;

                return new LiveWindowData(
                    window: $window,
                    currentTicket: $ticket,
                    assignedAt: $current?->assigned_at,
                );
            })
            ->all();
    }
}
