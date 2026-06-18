<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Http\Requests\Location\CheckinRequest;

/**
 * Validated QR check-in input (task 014). `ticketNumber` is parsed from the QR
 * payload identifying which ticket is arriving; the coordinates are the
 * student's raw GPS, verified server-side against the office radius.
 */
final readonly class CheckinData
{
    public function __construct(
        public string $ticketNumber,
        public float $latitude,
        public float $longitude,
    ) {}

    public static function fromRequest(CheckinRequest $request): self
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        return new self(
            ticketNumber: (string) $validated['ticket_number'],
            latitude: (float) $validated['latitude'],
            longitude: (float) $validated['longitude'],
        );
    }
}
