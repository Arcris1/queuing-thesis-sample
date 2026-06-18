<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Http\Requests\Heartbeat\HeartbeatRequest;

/**
 * Validated heartbeat input (task 015, plan §9). Every field is optional — the
 * app sends a lightweight liveness ping every 30 s; battery/network are telemetry
 * and the coordinates, when present, let the heartbeat double as a location ping
 * (reusing the geofence pipeline, never duplicating the distance math).
 */
final readonly class HeartbeatData
{
    public function __construct(
        public ?int $batteryLevel = null,
        public ?string $networkStatus = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {}

    public static function fromRequest(HeartbeatRequest $request): self
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        return new self(
            batteryLevel: isset($validated['battery_level']) ? (int) $validated['battery_level'] : null,
            networkStatus: isset($validated['network_status']) ? (string) $validated['network_status'] : null,
            latitude: isset($validated['latitude']) ? (float) $validated['latitude'] : null,
            longitude: isset($validated['longitude']) ? (float) $validated['longitude'] : null,
        );
    }

    /**
     * Whether a usable GPS sample was supplied so the heartbeat can double as a
     * location ping.
     */
    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
