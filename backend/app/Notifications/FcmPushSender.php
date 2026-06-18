<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DTOs\PushMessageData;
use App\Notifications\Contracts\PushSender;
use Psr\Log\LoggerInterface;

/**
 * Firebase Cloud Messaging sender (task 020) — used when `services.fcm.driver`
 * is `fcm`. It stays SAFE without credentials: if the project id or the
 * service-account file is missing/unreadable, or the message has no token, it
 * no-ops (logging a warning) rather than throwing. This lets the driver be
 * flipped on in any environment without breaking the request/queue path.
 *
 * The actual HTTP v1 call is left as a clearly-marked stub: it requires a real
 * Google service-account credential to mint an OAuth token and POST to
 * https://fcm.googleapis.com/v1/projects/{projectId}/messages:send. Wire that in
 * when credentials land (the persisted `push_notifications` row + this transport
 * seam already model everything around it).
 */
final class FcmPushSender implements PushSender
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ?string $projectId,
        private readonly ?string $credentialsPath,
    ) {}

    public function send(PushMessageData $message): void
    {
        if (! $message->hasToken()) {
            // A student without a registered device — nothing to send, not an error.
            return;
        }

        if (! $this->isConfigured()) {
            $this->logger->warning('push.fcm.skipped_unconfigured', [
                'user_id' => $message->userId,
                'type' => $message->type->value,
                'reason' => 'FCM project_id/credentials not configured',
            ]);

            return;
        }

        // STUB (task 020): build a Google OAuth2 access token from the service
        // account at $this->credentialsPath, then POST the v1 message payload:
        //
        //   POST https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send
        //   { "message": { "token": $message->fcmToken,
        //                   "notification": { "title": ..., "body": ... },
        //                   "data": $message->data } }
        //
        // Implement with Http::withToken(...)->post(...) once a credential is
        // provisioned. Until then we log so the path is observable in dev.
        $this->logger->info('push.fcm.dispatch', [
            'user_id' => $message->userId,
            'type' => $message->type->value,
            'project_id' => $this->projectId,
            'title' => $message->title,
            'body' => $message->body,
        ]);
    }

    private function isConfigured(): bool
    {
        return $this->projectId !== null
            && $this->projectId !== ''
            && $this->credentialsPath !== null
            && $this->credentialsPath !== ''
            && is_readable($this->credentialsPath);
    }
}
