<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\JoinQueueData;
use App\Exceptions\NotInQueueException;
use App\Http\Requests\Queue\JoinQueueRequest;
use App\Http\Resources\LeftTicketResource;
use App\Http\Resources\OfficeCurrentResource;
use App\Http\Resources\QueueEstimateResource;
use App\Http\Resources\QueueTicketResource;
use App\Models\User;
use App\Services\QueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class QueueController extends Controller
{
    public function __construct(
        private readonly QueueService $queueService,
    ) {}

    /**
     * POST /api/queue/join — join the chosen service's queue group (task 008).
     */
    public function join(JoinQueueRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth('api')->user();

        $status = $this->queueService->join($user, JoinQueueData::fromRequest($request));

        return QueueTicketResource::make($status)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * POST /api/queue/leave — leave the current active queue (task 009).
     */
    public function leave(): JsonResponse
    {
        /** @var User $user */
        $user = auth('api')->user();

        $ticket = $this->queueService->leave($user);

        return LeftTicketResource::make($ticket)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * GET /api/queue/status — the user's live ticket status, or data: null (task 010).
     */
    public function status(): JsonResponse
    {
        /** @var User $user */
        $user = auth('api')->user();

        $status = $this->queueService->statusFor($user);

        if ($status === null) {
            return new JsonResponse(['data' => null], Response::HTTP_OK);
        }

        return QueueTicketResource::make($status)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * GET /api/queue/estimate — AI-predicted wait time + confidence for the user's
     * active ticket (task 024). 404 when they hold no active ticket.
     */
    public function estimate(): JsonResponse
    {
        /** @var User $user */
        $user = auth('api')->user();

        $prediction = $this->queueService->estimateFor($user);

        if ($prediction === null) {
            throw new NotInQueueException;
        }

        return QueueEstimateResource::make($prediction)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * GET /api/queue/current — public per-queue-group "now serving" board (task 011).
     */
    public function current(Request $request): JsonResponse
    {
        $officeId = $request->query('office_id');

        $offices = $this->queueService->current(
            $officeId !== null ? (int) $officeId : null,
        );

        return OfficeCurrentResource::collection($offices)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
