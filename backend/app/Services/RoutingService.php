<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TicketStatus;
use App\Enums\WindowStatus;
use App\Events\QueueUpdated;
use App\Events\TicketCalled;
use App\Models\LocationLog;
use App\Models\Office;
use App\Models\QueueGroup;
use App\Models\QueueTicket;
use App\Models\ServiceHistory;
use App\Models\Window;
use App\Models\WindowAssignment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The window routing engine (research contribution #1, plan §5.3 / §5.4).
 *
 * The system maintains per-queue-group waiting lines — NOT per-window queues.
 * When a window becomes available, it is assigned the oldest *eligible* waiting
 * ticket across the queue groups it serves (priority desc, then FIFO by
 * joined_at). Because the candidate set is read live from the
 * `window_queue_groups` pivot, attaching another queue group to an idle window
 * (task 043) immediately widens what it can be assigned — no code change (§5.4).
 *
 * Selection + assignment happen in a single transaction with row locking so two
 * windows can never grab the same ticket.
 */
final class RoutingService
{
    public function __construct(
        private readonly GeofenceService $geofence,
        private readonly PresenceService $presence,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Assign the oldest eligible waiting ticket across the window's queue groups
     * and open a `window_assignments` row for it.
     *
     * Enforces at most one open assignment per window: if the window already has
     * an unfinished assignment, nothing is reassigned and that current ticket's
     * fresh state is returned.
     *
     * @return WindowAssignment|null the open assignment, or null when no eligible
     *                               ticket exists in the window's groups
     */
    public function assignNext(Window $window): ?WindowAssignment
    {
        $assignment = DB::transaction(function () use ($window): ?WindowAssignment {
            // One open assignment per window — do not reassign over a live ticket.
            /** @var WindowAssignment|null $existing */
            $existing = $window->assignments()
                ->open()
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $queueGroupIds = $window->queueGroups()
                ->pluck('queue_groups.id')
                ->all();

            if ($queueGroupIds === []) {
                return null;
            }

            $ticket = $this->lockNextEligibleTicket($queueGroupIds);

            if ($ticket === null) {
                return null;
            }

            $ticket->update([
                'status' => TicketStatus::Serving,
                'called_at' => now(),
            ]);

            $window->update(['status' => WindowStatus::Open]);

            /** @var WindowAssignment $assignment */
            $assignment = $window->assignments()->create([
                'ticket_id' => $ticket->id,
                'assigned_at' => now(),
                'served_at' => null,
            ]);

            $assignment->setRelation('ticket', $ticket);

            return $assignment;
        });

        // After commit (subscribers never see uncommitted state): announce the
        // call on the student + board channels (task 019) and push the personal
        // "proceed" notification (task 020). The group line also changed (one
        // fewer waiting) → QueueUpdated for the boards.
        if ($assignment !== null) {
            $this->announceCall($assignment);
            $this->broadcastQueueUpdated($assignment->ticket);
        }

        return $assignment;
    }

    /**
     * Complete the window's current ticket: close the open assignment and mark
     * the ticket Served. Returns the served ticket, or null if the window had no
     * open assignment.
     *
     * Captures one `service_history` row per serve INSIDE the same transaction
     * (task 022, plan §10): the per-transaction outcome the AI wait-time model
     * trains on, denormalized with the features it needs (office / queue group /
     * service / window, served_at, duration, day/hour, active windows serving the
     * group at serve time).
     */
    public function serve(Window $window): ?QueueTicket
    {
        $ticket = DB::transaction(function () use ($window): ?QueueTicket {
            $assignment = $this->lockOpenAssignment($window);

            if ($assignment === null) {
                return null;
            }

            /** @var QueueTicket $ticket */
            $ticket = $assignment->ticket;

            $servedAt = now();

            $ticket->update([
                'status' => TicketStatus::Served,
                'served_at' => $servedAt,
            ]);

            $assignment->update(['served_at' => $servedAt]);

            $window->update(['status' => WindowStatus::Idle]);

            // service_history capture (task 022) — inside the transaction so a
            // committed serve always has its training row, and a rollback never
            // leaves an orphaned one.
            $this->recordServiceHistory($window, $ticket, $servedAt);

            return $ticket;
        });

        // The line advanced (task 019): refresh the boards' now-serving/waiting.
        if ($ticket !== null) {
            $this->broadcastQueueUpdated($ticket);
        }

        return $ticket;
    }

    /**
     * Write the `service_history` row for a just-served ticket (task 022, plan
     * §10). Features are captured denormalized at serve time so training reads
     * them directly:
     *
     *   - duration_minutes: minutes the call took, from the ticket's called_at to
     *     served_at; guarded against null/zero/negative to a 1-minute floor so a
     *     missing/instant timestamp never poisons the regression target.
     *   - active_windows: how many windows attached to this ticket's queue group
     *     are currently Open — the divisor in the (people × avg) ÷ windows signal.
     */
    private function recordServiceHistory(Window $window, QueueTicket $ticket, \Illuminate\Support\Carbon $servedAt): void
    {
        $ticket->loadMissing('queueGroup');

        $minDuration = (float) config('queue_system.prediction.min_duration_minutes', 1);

        $duration = $ticket->called_at !== null
            ? $ticket->called_at->diffInSeconds($servedAt) / 60.0
            : $minDuration;
        $duration = max($minDuration, round($duration, 2));

        $activeWindows = $ticket->queueGroup->windows()
            ->where('windows.status', WindowStatus::Open)
            ->count();
        // The serving window has just gone Idle; count it as a window that served
        // this group so the signal reflects capacity during the transaction.
        $activeWindows = max(1, $activeWindows + 1);

        ServiceHistory::create([
            'office_id' => $ticket->queueGroup->office_id,
            'queue_group_id' => $ticket->queue_group_id,
            'service_id' => $ticket->service_id,
            'window_id' => $window->id,
            'served_at' => $servedAt,
            'duration_minutes' => $duration,
            'day_of_week' => (int) $servedAt->dayOfWeek,
            'hour_of_day' => (int) $servedAt->hour,
            'active_windows' => $activeWindows,
        ]);
    }

    /**
     * Skip the window's current ticket (no-show / away → Skipped per task 017's
     * current semantics), close its assignment, then immediately attempt to
     * assign the next eligible ticket. Returns the newly assigned ticket, or null
     * when nothing eligible remains.
     */
    public function skip(Window $window): ?QueueTicket
    {
        /** @var QueueTicket|null $skipped */
        $skipped = DB::transaction(function () use ($window): ?QueueTicket {
            $assignment = $this->lockOpenAssignment($window);

            if ($assignment === null) {
                return null;
            }

            /** @var QueueTicket $ticket */
            $ticket = $assignment->ticket;

            // A manual staff skip is a deliberate no-show call → terminal Skipped.
            // This is distinct from the automatic away/offline path (task 017),
            // which routes borderline tickets through grace → Standby (recoverable)
            // inside lockNextEligibleTicket() before they are ever assigned.
            $ticket->update(['status' => TicketStatus::Skipped]);

            $assignment->update(['served_at' => now()]);

            $window->update(['status' => WindowStatus::Idle]);

            return $ticket;
        });

        // After commit (task 019): the skipped ticket left the line. assignNext()
        // below additionally broadcasts TicketCalled + QueueUpdated for whoever it
        // promotes next, so the board sees both the removal and the new call.
        if ($skipped !== null) {
            $this->broadcastQueueUpdated($skipped);
        }

        return $this->assignNext($window)?->ticket;
    }

    /**
     * Re-announce the window's current assignment without changing any state.
     * Returns the current ticket, or null when the window has no open assignment.
     */
    public function recall(Window $window): ?QueueTicket
    {
        /** @var WindowAssignment|null $assignment */
        $assignment = $window->currentAssignment()
            ->with('ticket')
            ->first();

        if ($assignment === null) {
            return null;
        }

        // No state change — just re-announce the existing call (task 019/020): the
        // student gets the "proceed" event/push again and the boards refresh.
        $this->announceCall($assignment);

        /** @var QueueTicket $ticket */
        $ticket = $assignment->ticket;

        return $ticket;
    }

    /**
     * Select and lock the oldest eligible assignable ticket across the given queue
     * groups. Candidates are ordered by the routing rule (priority desc, Ready
     * before Waiting, FIFO by joined_at) and locked with `lockForUpdate` so a
     * concurrent available-call on another window cannot grab the same ticket.
     *
     * We scan locked candidates in order:
     *   - a fully eligible ticket (present Active + in-range) is returned immediately;
     *   - a borderline ticket (Away/Offline at its turn) is NOT silently skipped —
     *     it is offered a reconnect grace window (task 017, plan §9/§11). While its
     *     grace is still running we step past it so the line keeps moving; once its
     *     grace has elapsed without recovery we move it to Standby and continue.
     * So an absent student never blocks the people behind them, yet gets their
     * 2-minute chance to reconnect before losing their place.
     *
     * @param  array<int, int>  $queueGroupIds
     */
    private function lockNextEligibleTicket(array $queueGroupIds): ?QueueTicket
    {
        /** @var Collection<int, QueueTicket> $candidates */
        $candidates = QueueTicket::query()
            ->waitingEligibleOldest($queueGroupIds)
            ->with(['latestHeartbeat', 'latestLocationLog', 'queueGroup.office'])
            ->lockForUpdate()
            ->get();

        foreach ($candidates as $candidate) {
            if ($this->isEligible($candidate)) {
                return $candidate;
            }

            // Borderline: in-range but Away/Offline. Apply the reconnect-grace
            // state machine rather than skipping outright.
            if ($this->isWithinGeofence($candidate)) {
                $this->applyReconnectGrace($candidate);
            }

            // Either way this candidate is not callable right now — keep scanning.
        }

        return null;
    }

    /**
     * Drive a borderline ticket through the reconnect-grace state machine (task
     * 017): open a grace window the first time it is reached while ineligible, then
     * — once that window has elapsed without the student returning — send it to
     * Standby so it leaves the front of the line but can still be reinstated when
     * they come back (a fresh heartbeat). Runs inside the same assignment
     * transaction (the candidate is already row-locked).
     */
    private function applyReconnectGrace(QueueTicket $ticket): void
    {
        if ($this->presence->graceExpired($ticket)) {
            $this->presence->moveToStandby($ticket);

            return;
        }

        if ($ticket->grace_until === null) {
            // offerGrace() returns true only when a *new* grace window opened, so
            // the one-time reconnect warning push fires exactly once (task 020).
            if ($this->presence->offerGrace($ticket)) {
                $this->notifications->reconnectWarning($ticket);
            }
        }
    }

    /**
     * Eligibility seam (plan §5.3) — tasks 013 (geofence) and 016/017 (presence)
     * tighten this predicate without restructuring the engine.
     *
     * A ticket is eligible to be called *now* when:
     *   - status is assignable — Ready (checked-in, on-site) OR Waiting. A Ready
     *     ticket is preferred over Waiting at equal priority by the query scope, so
     *     check-in (task 014) genuinely advances a student (task 017 resolution of
     *     the Ready-vs-routing flag), AND
     *   - presence is Active (best-effort via PresenceService: derived from the
     *     latest heartbeat if one exists; no heartbeat yet → treated as Active so
     *     the engine works before the heartbeat pipeline reliably reports), AND
     *   - the ticket is within the office geofence per {@see isWithinGeofence()}.
     *
     * When a ticket is eligible we also clear any leftover reconnect-grace window —
     * the student became present+in-range within grace, so they keep their place.
     */
    protected function isEligible(QueueTicket $ticket): bool
    {
        if (! in_array($ticket->status, [TicketStatus::Ready, TicketStatus::Waiting], true)) {
            return false;
        }

        // Presence gating (task 016/017): centralized via PresenceService so the
        // Active→Away→Offline→Removed rule lives in one place.
        if (! $this->presence->isPresent($ticket)) {
            return false;
        }

        // Geofence gating (task 013): require a recent, in-range location sample.
        if (! $this->isWithinGeofence($ticket)) {
            return false;
        }

        // Recovered within grace: clear the window so the student keeps their slot.
        if ($ticket->grace_until !== null || $ticket->grace_offered_at !== null) {
            $this->presence->reinstateOnReturn($ticket);
        }

        return true;
    }

    /**
     * Geofence eligibility decision (plan §8) — the single point that decides
     * whether a ticket's location qualifies it for assignment. Kept separate so
     * the no-log policy is a one-line flip.
     *
     * Rules:
     *   - A location sample counts only when it is recent enough (no older than
     *     `queue_system.geofence.max_age_seconds`) — a stale "within range"
     *     reading is treated as no signal, not as proof of presence.
     *   - A fresh sample is eligible iff its server-computed distance is within
     *     the office radius (distance is recomputed here from stored office
     *     coordinates; the persisted `distance_m` is never trusted blindly).
     *   - NO usable sample → governed by `queue_system.geofence.require_location`:
     *       false (default, pre-strict) → eligible (best-effort, lets the engine
     *               run before the app reliably reports GPS).
     *       true  → ineligible (strict — assignment requires a proven in-range
     *               sample). Flip the config to switch policy; no code change.
     */
    protected function isWithinGeofence(QueueTicket $ticket): bool
    {
        /** @var LocationLog|null $log */
        $log = $ticket->latestLocationLog;

        $maxAge = (int) config('queue_system.geofence.max_age_seconds');
        $requireLocation = (bool) config('queue_system.geofence.require_location');

        $isFresh = $log !== null
            && $log->recorded_at !== null
            && $log->recorded_at->gt(now()->subSeconds($maxAge));

        if (! $isFresh) {
            // No usable signal: apply the no-log policy switch.
            return ! $requireLocation;
        }

        /** @var Office $office */
        $office = $ticket->queueGroup->office;

        return $this->geofence->isWithinOffice(
            $office,
            (float) $log->latitude,
            (float) $log->longitude,
        );
    }

    /**
     * Fetch + lock the window's single open assignment (with its ticket locked
     * too) for serve/skip mutations.
     */
    private function lockOpenAssignment(Window $window): ?WindowAssignment
    {
        /** @var WindowAssignment|null $assignment */
        $assignment = $window->assignments()
            ->open()
            ->lockForUpdate()
            ->first();

        if ($assignment === null) {
            return null;
        }

        $assignment->load('ticket');

        return $assignment;
    }

    /**
     * Announce a window's call (assignNext / recall): broadcast TicketCalled to
     * the student + board channels (task 019) and push the personal "proceed"
     * notification (task 020). Eager-load the relations both consume once here so
     * neither re-queries per accessor.
     */
    private function announceCall(WindowAssignment $assignment): void
    {
        $assignment->loadMissing([
            'window.office',
            'ticket.queueGroup.office',
            'ticket.service',
            'ticket.user',
        ]);

        /** @var QueueTicket $ticket */
        $ticket = $assignment->ticket;
        /** @var Window $window */
        $window = $assignment->window;

        TicketCalled::dispatch($assignment);
        $this->notifications->proceed($ticket, $window);
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
