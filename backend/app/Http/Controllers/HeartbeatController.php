<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\HeartbeatData;
use App\Http\Requests\Heartbeat\HeartbeatRequest;
use App\Http\Resources\PresenceResource;
use App\Models\User;
use App\Services\PresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class HeartbeatController extends Controller
{
    public function __construct(
        private readonly PresenceService $presenceService,
    ) {}

    /**
     * POST /api/heartbeat — record the student's periodic liveness ping for their
     * active ticket and return the server-derived presence status (task 015).
     * When coordinates are included the heartbeat also logs a location sample,
     * reusing the geofence pipeline (plan §8/§9).
     */
    public function store(HeartbeatRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth('api')->user();

        $result = $this->presenceService->heartbeat($user, HeartbeatData::fromRequest($request));

        return PresenceResource::make($result)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
