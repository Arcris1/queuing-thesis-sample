<?php

declare(strict_types=1);

namespace App\Notifications\Contracts;

use App\DTOs\PushMessageData;

/**
 * Transport seam for outbound push (task 020). NotificationService builds the
 * copy + persists the `push_notifications` row, then hands the message here to
 * be delivered. The concrete sender is chosen by config (`services.fcm.driver`)
 * and bound in AppServiceProvider — `log` in dev/CI, `fcm` in production — so
 * nothing in the app depends on a real FCM connection.
 */
interface PushSender
{
    /**
     * Deliver one push. Implementations MUST be tolerant of a missing/blank
     * token (no-op, never throw) so a student without a registered device never
     * breaks the queue flow.
     */
    public function send(PushMessageData $message): void;
}
