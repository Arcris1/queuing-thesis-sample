<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\PushMessageData;
use App\Enums\NotificationType;
use App\Models\Office;
use App\Models\PushNotification;
use App\Models\QueueGroup;
use App\Models\QueueTicket;
use App\Models\User;
use App\Models\Window;
use App\Notifications\Contracts\PushSender;

/**
 * Smart queue notifications (task 020, plan §12). Single source of truth for
 * outbound push: every public method builds the user-facing copy from the
 * {@see NotificationType} enum, persists a `push_notifications` row (so the
 * student's notification history is durable regardless of delivery), then hands
 * a {@see PushMessageData} to the injected {@see PushSender} transport.
 *
 * The transport is config-selected (log vs FCM) and tolerant of missing tokens,
 * so calling these from the queue/presence flows is always safe.
 */
final class NotificationService
{
    public function __construct(
        private readonly PushSender $sender,
        private readonly WaitTimePredictor $predictor,
    ) {}

    /**
     * "Please proceed to {office} — {window}" — fired when a window is assigned a
     * ticket (the personal half of {@see \App\Events\TicketCalled}). Sent once per
     * call by the caller's hook point (assignNext / recall).
     */
    public function proceed(QueueTicket $ticket, Window $window): PushNotification
    {
        $ticket->loadMissing(['queueGroup.office', 'user']);

        /** @var QueueGroup $group */
        $group = $ticket->queueGroup;
        /** @var Office $office */
        $office = $group->office;

        $message = sprintf(
            'You are being called. Please proceed to %s — %s (ticket %s).',
            $office->name,
            $window->name,
            $ticket->ticket_number,
        );

        return $this->dispatch(
            $ticket->user,
            NotificationType::Proceed,
            'It\'s your turn',
            $message,
            ['ticket_id' => (string) $ticket->id, 'window' => $window->name],
        );
    }

    /**
     * "You are now #{position}" — a position milestone as the student advances
     * (plan §12). Debounce at the call site: only invoke when a milestone
     * threshold (queue_system.notifications.position_milestones) is crossed.
     */
    public function positionMilestone(QueueTicket $ticket, int $peopleAhead): PushNotification
    {
        $ticket->loadMissing(['user', 'queueGroup.office']);

        $position = $peopleAhead + 1;
        $message = $peopleAhead === 0
            ? 'You are next. Stay nearby and keep the app open.'
            : sprintf('You are now #%d in line (%d ahead).', $position, $peopleAhead);

        return $this->dispatch(
            $ticket->user,
            NotificationType::PositionUpdate,
            'Queue update',
            $message,
            ['ticket_id' => (string) $ticket->id, 'position' => (string) $position],
        );
    }

    /**
     * "Estimated wait: ~{n} min" — an ETA refresh (plan §12). The minutes value
     * is supplied by the caller (placeholder = people_ahead × avg_service_minutes
     * via {@see estimateMinutes()} until task 024's real prediction lands).
     */
    public function etaUpdate(QueueTicket $ticket, int $minutes): PushNotification
    {
        $ticket->loadMissing('user');

        $message = $minutes <= 0
            ? 'You are next — estimated wait under a minute.'
            : sprintf('Estimated wait: about %d minute%s.', $minutes, $minutes === 1 ? '' : 's');

        return $this->dispatch(
            $ticket->user,
            NotificationType::EtaUpdate,
            'Estimated wait',
            $message,
            ['ticket_id' => (string) $ticket->id, 'eta_minutes' => (string) $minutes],
        );
    }

    /**
     * One-time reconnect warning when a student is Away/Offline at call-time and
     * a grace window opens (task 017, plan §9/§11). Fired once per grace window.
     */
    public function reconnectWarning(QueueTicket $ticket): PushNotification
    {
        $ticket->loadMissing('user');

        return $this->dispatch(
            $ticket->user,
            NotificationType::ReconnectWarning,
            'Reconnect to keep your place',
            'You appear to be away. Reopen the app within 2 minutes or you may lose your place in line.',
            ['ticket_id' => (string) $ticket->id],
        );
    }

    /**
     * The real ETA for a ticket (task 024): delegates to the shared
     * {@see WaitTimePredictor} — the SAME code path as /queue/estimate and the
     * status `eta` field — so the push ETA, the API estimate, and the status ETA
     * can never disagree. Feed the result into {@see etaUpdate()}.
     */
    public function estimateMinutesForTicket(QueueTicket $ticket, int $peopleAhead): int
    {
        return $this->predictor->predictForTicket($ticket, $peopleAhead)->estimatedMinutes;
    }

    /**
     * Naive per-person ETA baseline (plan §12). Retained for callers/contexts that
     * only have a people-ahead count and no ticket; the model-backed path is
     * {@see estimateMinutesForTicket()}. Mirrors the window-unaware naive formula
     * the plan contrasts the model against (§10).
     */
    public function estimateMinutes(int $peopleAhead): int
    {
        $perPerson = (int) config('queue_system.notifications.avg_service_minutes');

        return $peopleAhead * $perPerson;
    }

    /**
     * Whether crossing from $previousPeopleAhead to $peopleAhead passes one of the
     * configured milestones (debounce helper for the call site). True when the new
     * people-ahead count is a milestone and we strictly improved past it.
     */
    public function crossedMilestone(int $previousPeopleAhead, int $peopleAhead): bool
    {
        if ($peopleAhead >= $previousPeopleAhead) {
            return false;
        }

        /** @var array<int, int> $milestones */
        $milestones = config('queue_system.notifications.position_milestones');

        return in_array($peopleAhead, $milestones, true);
    }

    /**
     * Persist the notification row and hand it to the transport. Centralized so
     * every notification is durably recorded with a consistent shape.
     *
     * @param  array<string, scalar>  $data
     */
    private function dispatch(
        User $user,
        NotificationType $type,
        string $title,
        string $body,
        array $data = [],
    ): PushNotification {
        /** @var PushNotification $record */
        $record = $user->pushNotifications()->create([
            'type' => $type,
            'message' => $body,
            'sent_at' => now(),
        ]);

        $this->sender->send(new PushMessageData(
            userId: $user->id,
            fcmToken: $user->fcm_token,
            type: $type,
            title: $title,
            body: $body,
            data: $data,
        ));

        return $record;
    }
}
