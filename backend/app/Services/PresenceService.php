<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\HeartbeatData;
use App\DTOs\LocationData;
use App\DTOs\PresenceResultData;
use App\Enums\PresenceStatus;
use App\Enums\TicketStatus;
use App\Events\PresenceChanged;
use App\Events\QueueUpdated;
use App\Exceptions\TicketNotFoundException;
use App\Models\Heartbeat;
use App\Models\QueueGroup;
use App\Models\QueueTicket;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Presence detection (research contribution #3, plan §9 / §15).
 *
 * A heartbeat (every 30 s) drives an Active → Away → Offline → Removed state
 * machine whose thresholds live entirely in config (queue_system.presence.*) — no
 * threshold is hard-coded here. This service is the single source of truth for:
 *
 *   - ingesting heartbeats (task 015),
 *   - deriving a ticket's PresenceStatus from its latest heartbeat (task 016),
 *   - reclaiming abandoned (Removed) tickets in the scheduled scan (task 016),
 *   - the away/offline reconnect-grace + standby decisions at call-time (task 017).
 */
final class PresenceService
{
    public function __construct(
        private readonly LocationService $location,
    ) {}

    /**
     * Ingest one heartbeat for the user's active ticket (task 015): record a
     * `heartbeats` row stamped `last_seen = now()`, optionally fold in a location
     * ping (reusing {@see LocationService::record()} so the Haversine math is never
     * duplicated), and return the freshly derived presence.
     *
     * Lightweight by design — one insert, one optional location insert, no N+1.
     *
     * @throws TicketNotFoundException when the user has no active ticket
     */
    public function heartbeat(User $user, HeartbeatData $data): PresenceResultData
    {
        $ticket = $this->activeTicketFor($user);

        if ($ticket === null) {
            throw new TicketNotFoundException;
        }

        /** @var Heartbeat $heartbeat */
        $heartbeat = Heartbeat::create([
            'user_id' => $user->id,
            'ticket_id' => $ticket->id,
            'last_seen' => now(),
            'battery_level' => $data->batteryLevel,
            'network_status' => $data->networkStatus,
        ]);

        // A heartbeat carrying GPS doubles as a location ping — reuse the geofence
        // pipeline rather than recomputing distance here (plan §8/§9).
        if ($data->hasLocation()) {
            $this->location->record($user, new LocationData(
                latitude: (float) $data->latitude,
                longitude: (float) $data->longitude,
                ticketId: $ticket->id,
            ));
        }

        // A live heartbeat means the student is back — reinstate a Standby ticket
        // (missed-call recovery, task 017) so it re-enters the line as Waiting and
        // clear any stale grace window.
        $this->reinstateOnReturn($ticket);

        return new PresenceResultData(
            heartbeat: $heartbeat,
            presenceStatus: $heartbeat->presence_status,
            ticket: $ticket->refresh()->load(['queueGroup.office', 'service']),
        );
    }

    /**
     * Derive a ticket's presence from the age of its latest heartbeat against the
     * config thresholds (task 016, plan §9): Active <2m, Away >2m, Offline >5m,
     * Removed >10m. A ticket with no heartbeat yet is treated as Active
     * (best-effort) so the engine works before the app's heartbeat pipeline lands.
     */
    public function evaluate(QueueTicket $ticket): PresenceStatus
    {
        $heartbeat = $ticket->relationLoaded('latestHeartbeat')
            ? $ticket->latestHeartbeat
            : $ticket->latestHeartbeat()->first();

        if ($heartbeat === null) {
            return PresenceStatus::Active;
        }

        return $this->statusFromLastSeen($heartbeat->last_seen);
    }

    /**
     * Map a `last_seen` timestamp to a PresenceStatus using the config thresholds.
     * Centralized so the Heartbeat accessor, the scheduled scan, and the routing
     * grace logic all agree on one rule.
     */
    public function statusFromLastSeen(CarbonInterface $lastSeen): PresenceStatus
    {
        $secondsSince = $lastSeen->diffInSeconds(now());

        return match (true) {
            $secondsSince > (int) config('queue_system.presence.removed_after_seconds') => PresenceStatus::Removed,
            $secondsSince > (int) config('queue_system.presence.offline_after_seconds') => PresenceStatus::Offline,
            $secondsSince > (int) config('queue_system.presence.away_after_seconds') => PresenceStatus::Away,
            default => PresenceStatus::Active,
        };
    }

    /**
     * Scheduled scan (task 016): reclaim abandoned tickets. Any still-in-line
     * ticket (Waiting/Ready/Standby) whose presence has decayed to Removed
     * (heartbeat older than the removed threshold) is moved out of the active
     * queue to TicketStatus::Skipped and its slot freed.
     *
     * Idempotent (a ticket already out of line is never touched) and batched to
     * avoid loading every ticket at once. Returns the number of tickets reclaimed.
     */
    public function reclaimAbandoned(int $batchSize = 200): int
    {
        $reclaimed = 0;

        QueueTicket::query()
            ->whereIn('status', [TicketStatus::Waiting, TicketStatus::Ready, TicketStatus::Standby])
            ->forToday()
            ->with('latestHeartbeat')
            ->chunkById($batchSize, function ($tickets) use (&$reclaimed): void {
                foreach ($tickets as $ticket) {
                    if ($this->evaluate($ticket) !== PresenceStatus::Removed) {
                        continue;
                    }

                    $changed = DB::transaction(function () use ($ticket): bool {
                        /** @var QueueTicket|null $fresh */
                        $fresh = QueueTicket::query()
                            ->whereKey($ticket->id)
                            ->whereIn('status', [
                                TicketStatus::Waiting,
                                TicketStatus::Ready,
                                TicketStatus::Standby,
                            ])
                            ->lockForUpdate()
                            ->first();

                        // Idempotent: a concurrent serve/leave may have already
                        // moved it out of line.
                        if ($fresh === null) {
                            return false;
                        }

                        $fresh->update([
                            'status' => TicketStatus::Skipped,
                            'grace_until' => null,
                            'grace_offered_at' => null,
                        ]);

                        return true;
                    });

                    if ($changed) {
                        $reclaimed++;

                        // After commit (task 019): the abandoned ticket was removed
                        // from the line — repaint the staff row (presence) and refresh
                        // the board summary. Subscribers never see uncommitted state.
                        $ticket->refresh();
                        event(PresenceChanged::forTicket($ticket, PresenceStatus::Removed));
                        $this->broadcastQueueUpdated($ticket);
                    }
                }
            });

        return $reclaimed;
    }

    /**
     * Whether a ticket is eligible to be *called now* (task 017): on-site/checked
     * in or waiting, present (Active), and within range. Used by the routing
     * engine's grace decision. Geofence is delegated to RoutingService so the
     * radius math stays in one place — here we own the presence half.
     */
    public function isPresent(QueueTicket $ticket): bool
    {
        return $this->evaluate($ticket)->isEligible();
    }

    /**
     * Open (or refresh) a reconnect-grace window for a borderline ticket whose
     * student is Away/Offline at call-time (task 017, plan §9/§11). The first time
     * we offer grace we stamp `grace_offered_at` so the warning notification fires
     * exactly once per window; `grace_until` is the deadline after which the ticket
     * is sent to Standby.
     *
     * Returns true when a *new* grace window was opened (caller should fire the
     * one-time warning), false when an existing, unexpired window is still running.
     */
    public function offerGrace(QueueTicket $ticket): bool
    {
        $graceSeconds = (int) config('queue_system.reconnect_grace_seconds');

        return DB::transaction(function () use ($ticket, $graceSeconds): bool {
            /** @var QueueTicket $fresh */
            $fresh = QueueTicket::query()
                ->whereKey($ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

            // An unexpired window is already running — do not re-warn.
            if ($fresh->grace_until !== null && $fresh->grace_until->isFuture()) {
                $ticket->setRawAttributes($fresh->getAttributes());

                return false;
            }

            $fresh->update([
                'grace_until' => now()->addSeconds($graceSeconds),
                'grace_offered_at' => now(),
            ]);

            $ticket->setRawAttributes($fresh->getAttributes());

            // Push hook (task 020): notify the student to reconnect within the
            // grace window — call the notify dispatcher here once it exists.

            return true;
        });
    }

    /**
     * Whether a ticket's reconnect grace has elapsed without recovery (task 017):
     * it was offered a window and that deadline is now in the past. The routing
     * engine standbys such a ticket and advances.
     */
    public function graceExpired(QueueTicket $ticket): bool
    {
        return $ticket->grace_until !== null && $ticket->grace_until->isPast();
    }

    /**
     * Send a ticket to Standby after its grace lapsed without the student becoming
     * Active+in-range (task 017). Standby is NOT terminal: the student can return
     * (a fresh heartbeat) and be reinstated as Waiting via {@see reinstateOnReturn()}.
     * Distinct from a voluntary /queue/leave, which sets Skipped.
     */
    public function moveToStandby(QueueTicket $ticket): void
    {
        DB::transaction(function () use ($ticket): void {
            /** @var QueueTicket $fresh */
            $fresh = QueueTicket::query()
                ->whereKey($ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

            $fresh->update([
                'status' => TicketStatus::Standby,
                'grace_until' => null,
                'grace_offered_at' => null,
            ]);

            $ticket->setRawAttributes($fresh->getAttributes());
        });

        // After commit (task 019): the ticket dropped to Standby — repaint the
        // staff row and refresh the board summary.
        event(PresenceChanged::forTicket($ticket, PresenceStatus::Offline));
        $this->broadcastQueueUpdated($ticket);
    }

    /**
     * Reinstate a returning student's ticket (task 017): a Standby ticket whose
     * student is present again (a fresh heartbeat just landed) re-enters the line
     * as Waiting, keeping its original joined_at so it lands near where it was
     * (priority/joined_at ordering). Any leftover grace state is cleared. No-op for
     * tickets that are not Standby.
     */
    public function reinstateOnReturn(QueueTicket $ticket): void
    {
        if ($ticket->status === TicketStatus::Standby) {
            DB::transaction(function () use ($ticket): void {
                /** @var QueueTicket|null $fresh */
                $fresh = QueueTicket::query()
                    ->whereKey($ticket->id)
                    ->where('status', TicketStatus::Standby)
                    ->lockForUpdate()
                    ->first();

                if ($fresh === null) {
                    return;
                }

                $fresh->update([
                    'status' => TicketStatus::Waiting,
                    'grace_until' => null,
                    'grace_offered_at' => null,
                ]);

                $ticket->setRawAttributes($fresh->getAttributes());
            });

            return;
        }

        // Not on standby: just clear any stale grace window now that we have a
        // fresh sign of life.
        if ($ticket->grace_until !== null || $ticket->grace_offered_at !== null) {
            $ticket->update([
                'grace_until' => null,
                'grace_offered_at' => null,
            ]);
        }
    }

    /**
     * The user's current active (Waiting/Ready/Serving/Standby) ticket today, with
     * the relations the heartbeat/location pipeline needs eager-loaded.
     */
    private function activeTicketFor(User $user): ?QueueTicket
    {
        /** @var QueueTicket|null $ticket */
        $ticket = QueueTicket::query()
            ->where('user_id', $user->id)
            ->whereIn('status', TicketStatus::active())
            ->forToday()
            ->with('queueGroup.office')
            ->orderByDesc('joined_at')
            ->first();

        return $ticket;
    }

    /**
     * Broadcast a lightweight board refresh for a ticket's queue group (task 019).
     */
    private function broadcastQueueUpdated(QueueTicket $ticket): void
    {
        $ticket->loadMissing('queueGroup');

        /** @var QueueGroup $group */
        $group = $ticket->queueGroup;

        event(QueueUpdated::forGroup($group));
    }
}
