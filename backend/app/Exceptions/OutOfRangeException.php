<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * Thrown when a student attempts to check in (task 014) while their GPS position
 * is outside the office's geofence radius. The distance is decided server-side;
 * the client cannot bypass this by sending a forged distance.
 */
class OutOfRangeException extends RuntimeException
{
    public function __construct(
        private readonly float $distanceMeters,
        private readonly float $radiusMeters,
        string $message = 'You are outside the office area. Move closer to check in.',
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return new JsonResponse(
            [
                'message' => $this->getMessage(),
                'distance_m' => round($this->distanceMeters, 2),
                'radius_m' => $this->radiusMeters,
            ],
            Response::HTTP_CONFLICT,
        );
    }
}
