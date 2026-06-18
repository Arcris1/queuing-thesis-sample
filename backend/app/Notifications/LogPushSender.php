<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DTOs\PushMessageData;
use App\Notifications\Contracts\PushSender;
use Psr\Log\LoggerInterface;

/**
 * Default push sender (task 020): writes the notification to the log instead of
 * hitting FCM. This is what dev and CI use, so the queue/notification flow runs
 * end-to-end without any Firebase credentials or network.
 */
final class LogPushSender implements PushSender
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function send(PushMessageData $message): void
    {
        $this->logger->info('push.notification', [
            'user_id' => $message->userId,
            'type' => $message->type->value,
            'has_token' => $message->hasToken(),
            'title' => $message->title,
            'body' => $message->body,
            'data' => $message->data,
        ]);
    }
}
