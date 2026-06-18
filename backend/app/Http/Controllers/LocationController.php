<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\CheckinData;
use App\DTOs\LocationData;
use App\Http\Requests\Location\CheckinRequest;
use App\Http\Requests\Location\UpdateLocationRequest;
use App\Http\Resources\AssignedTicketResource;
use App\Http\Resources\LocationResultResource;
use App\Models\User;
use App\Services\LocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class LocationController extends Controller
{
    public function __construct(
        private readonly LocationService $locationService,
    ) {}

    /**
     * POST /api/location/update — record a GPS sample for the student's ticket
     * and return the server-decided distance/within-range signal (task 013).
     */
    public function update(UpdateLocationRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth('api')->user();

        $result = $this->locationService->record($user, LocationData::fromRequest($request));

        return LocationResultResource::make($result)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * POST /api/checkin — QR-based arrival: verify ownership + within-radius
     * server-side, mark the ticket Ready, return the updated ticket (task 014).
     */
    public function checkin(CheckinRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth('api')->user();

        $ticket = $this->locationService->checkin($user, CheckinData::fromRequest($request));

        return AssignedTicketResource::make($ticket)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
