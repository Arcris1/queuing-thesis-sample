<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\NotificationType;

/**
 * The shape of one outbound push notification handed to a {@see \App\Notifications\Contracts\PushSender}
 * (task 020). Decouples NotificationService (which builds copy + persists rows)
 * from the transport (log vs FCM), so the sender never touches Eloquent.
 */
final readonly class PushMessageData
{
    public function __construct(
        public int $userId,
        public ?string $fcmToken,
        public NotificationType $type,
        public string $title,
        public string $body,
        /** @var array<string, scalar> */
        public array $data = [],
    ) {}

    public function hasToken(): bool
    {
        return $this->fcmToken !== null && $this->fcmToken !== '';
    }
}
