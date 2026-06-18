<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Http\Requests\Queue\JoinQueueRequest;

/**
 * Validated input for joining a queue. The client only chooses a service — the
 * office and queue group are resolved server-side from it (task 008).
 */
final readonly class JoinQueueData
{
    public function __construct(
        public int $serviceId,
    ) {}

    public static function fromRequest(JoinQueueRequest $request): self
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        return new self(
            serviceId: (int) $validated['service_id'],
        );
    }
}
