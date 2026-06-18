<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\CheckinData;
use App\DTOs\LocationData;
use App\DTOs\LocationResultData;
use App\Enums\TicketStatus;
use App\Exceptions\OutOfRangeException;
use App\Exceptions\TicketNotFoundException;
use App\Models\LocationLog;
use App\Models\Office;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Geofencing pipeline (research contribution #2, plan §8): turn a raw GPS sample
 * into a server-decided distance/eligibility signal, log it, and gate arrival.
 *
 * The client never supplies a distance — every distance here is computed via
 * {@see GeofenceService} from stored office coordinates.
 */
final class LocationService
{
    public function __construct(
        private readonly GeofenceService $geofence,
    ) {}

    /**
     * Record a location sample for the user's ticket (task 013): resolve the
     * target office, compute the server-side distance, persist a `location_logs`
     * row, and return the authoritative within-range signal.
     *
     * @throws TicketNotFoundException when the user has no active ticket (or the
     *                                 given ticket is not their active one)
     */
    public function record(User $user, LocationData $data): LocationResultData
    {
        $ticket = $this->resolveActiveTicket($user, $data->ticketId);

        /** @var Office $office */
        $office = $ticket->queueGroup->office;

        $distance = $this->geofence->distanceToOffice($office, $data->latitude, $data->longitude);
        $radius = $this->geofence->radiusFor($office);

        $log = LocationLog::create([
            'user_id' => $user->id,
            'ticket_id' => $ticket->id,
            'latitude' => $data->latitude,
            'longitude' => $data->longitude,
            'distance_m' => round($distance, 2),
            'recorded_at' => now(),
        ]);

        return new LocationResultData(
            log: $log,
            office: $office,
            distanceMeters: round($distance, 2),
            withinRange: $distance <= $radius,
            radiusMeters: $radius,
        );
    }

    /**
     * QR check-in (task 014): verify the scanned ticket belongs to the student,
     * is still active, and the device is within the office radius — then mark the
     * ticket Ready. Logs the sample regardless so an out-of-range attempt is
     * auditable. All checks + the state transition run in one transaction.
     *
     * @throws TicketNotFoundException when the scanned number is not the user's active ticket
     * @throws OutOfRangeException when the device is outside the office radius
     */
    public function checkin(User $user, CheckinData $data): QueueTicket
    {
        $ticket = $this->resolveTicketByNumber($user, $data->ticketNumber);

        /** @var Office $office */
        $office = $ticket->queueGroup->office;

        $distance = $this->geofence->distanceToOffice($office, $data->latitude, $data->longitude);
        $radius = $this->geofence->radiusFor($office);

        // Audit every scan, including out-of-range attempts. Logged before any
        // throw so the sample survives a rejected (rolled-back) check-in.
        LocationLog::create([
            'user_id' => $user->id,
            'ticket_id' => $ticket->id,
            'latitude' => $data->latitude,
            'longitude' => $data->longitude,
            'distance_m' => round($distance, 2),
            'recorded_at' => now(),
        ]);

        if ($distance > $radius) {
            throw new OutOfRangeException($distance, $radius);
        }

        // Within range: arrival confirmed. Re-read + lock the ticket and flip it
        // to Ready in one transaction so a concurrent routing call sees a
        // consistent state. Ready signals the routing engine the student is
        // present at the office and ready to be called.
        return DB::transaction(function () use ($user, $data): QueueTicket {
            $ticket = $this->resolveTicketByNumber($user, $data->ticketNumber);

            $ticket->update(['status' => TicketStatus::Ready]);

            return $ticket;
        });
    }

    /**
     * The active ticket targeted by a location update: the explicitly-passed one
     * (must be the user's and still active) or, by default, the user's current
     * active ticket today.
     *
     * @throws TicketNotFoundException
     */
    private function resolveActiveTicket(User $user, ?int $ticketId): QueueTicket
    {
        $query = QueueTicket::query()
            ->where('user_id', $user->id)
            ->whereIn('status', TicketStatus::active())
            ->forToday()
            ->with('queueGroup.office');

        if ($ticketId !== null) {
            $query->whereKey($ticketId);
        }

        /** @var QueueTicket|null $ticket */
        $ticket = $query
            ->orderByDesc('joined_at')
            ->first();

        if ($ticket === null) {
            throw new TicketNotFoundException;
        }

        return $ticket;
    }

    /**
     * The user's active ticket matching a scanned ticket number, locked for the
     * check-in transition. Scoped to the user so a scanned number can never
     * check in someone else's ticket.
     *
     * @throws TicketNotFoundException
     */
    private function resolveTicketByNumber(User $user, string $ticketNumber): QueueTicket
    {
        /** @var QueueTicket|null $ticket */
        $ticket = QueueTicket::query()
            ->where('user_id', $user->id)
            ->where('ticket_number', $ticketNumber)
            ->whereIn('status', TicketStatus::active())
            ->forToday()
            ->with('queueGroup.office', 'service', 'user')
            ->lockForUpdate()
            ->first();

        if ($ticket === null) {
            throw new TicketNotFoundException;
        }

        return $ticket;
    }
}
