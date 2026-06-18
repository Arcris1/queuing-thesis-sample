<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Http\Requests\Location\UpdateLocationRequest;

/**
 * Validated GPS sample for a location update (task 013). The client sends only
 * raw coordinates and an optional ticket id — the server computes distance and
 * decides eligibility, never trusting any client-sent distance.
 */
final readonly class LocationData
{
    public function __construct(
        public float $latitude,
        public float $longitude,
        public ?int $ticketId = null,
    ) {}

    public static function fromRequest(UpdateLocationRequest $request): self
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        return new self(
            latitude: (float) $validated['latitude'],
            longitude: (float) $validated['longitude'],
            ticketId: isset($validated['ticket_id']) ? (int) $validated['ticket_id'] : null,
        );
    }
}
