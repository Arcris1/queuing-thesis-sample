<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Office;

/**
 * Server-side geofence math (research contribution #2, plan §8).
 *
 * The client only ever sends raw lat/lng — distance and within-radius
 * eligibility are decided HERE, never trusted from the client. Pure and
 * stateless: no Eloquent writes, no request state.
 */
final class GeofenceService
{
    /**
     * Mean Earth radius in meters (plan §8).
     */
    private const float EARTH_RADIUS_M = 6_371_000.0;

    /**
     * Great-circle distance in meters between two GPS points via the Haversine
     * formula (plan §8):
     *
     *   a = sin²(Δlat/2) + cos(lat1)·cos(lat2)·sin²(Δlng/2)
     *   d = 2R·asin(√a)
     */
    public function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_M * asin(min(1.0, sqrt($a)));
    }

    /**
     * Whether a point lies within `$radiusMeters` of the target point.
     */
    public function isWithinRadius(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2,
        float $radiusMeters,
    ): bool {
        return $this->distanceMeters($lat1, $lng1, $lat2, $lng2) <= $radiusMeters;
    }

    /**
     * Distance in meters from the given point to an office's stored coordinates.
     */
    public function distanceToOffice(Office $office, float $lat, float $lng): float
    {
        return $this->distanceMeters(
            (float) $office->latitude,
            (float) $office->longitude,
            $lat,
            $lng,
        );
    }

    /**
     * Whether the given point is within the office's configured geofence radius.
     * Honors `offices.geofence_radius_m`, falling back to the config default.
     */
    public function isWithinOffice(Office $office, float $lat, float $lng): bool
    {
        return $this->distanceToOffice($office, $lat, $lng) <= $this->radiusFor($office);
    }

    /**
     * The effective radius (meters) for an office: its own column when set,
     * otherwise the system default (`queue_system.geofence_radius_m`).
     */
    public function radiusFor(Office $office): float
    {
        return (float) ($office->geofence_radius_m ?? config('queue_system.geofence_radius_m'));
    }
}
