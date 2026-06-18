<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\NoActiveAssignmentException;
use App\Http\Requests\Window\RecallRequest;
use App\Http\Requests\Window\ServeRequest;
use App\Http\Requests\Window\SkipRequest;
use App\Http\Requests\Window\WindowAvailableRequest;
use App\Http\Resources\AssignedTicketResource;
use App\Models\QueueTicket;
use App\Services\RoutingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Staff window controls (task 021). Thin: each action authorizes via its Form
 * Request, delegates the routing/state logic to {@see RoutingService}, and
 * returns an API Resource in the standard `{ data: ... }` envelope.
 */
final class WindowController extends Controller
{
    public function __construct(
        private readonly RoutingService $routingService,
    ) {}

    /**
     * POST /api/windows/{window}/available — mark the window available and let the
     * routing engine assign the oldest eligible ticket across its queue groups.
     * Returns the assigned ticket, or `{ data: null }` when none is eligible.
     */
    public function available(WindowAvailableRequest $request): JsonResponse
    {
        $assignment = $this->routingService->assignNext($request->window());

        if ($assignment === null) {
            return new JsonResponse(['data' => null], Response::HTTP_OK);
        }

        return $this->ticketResponse($assignment->ticket);
    }

    /**
     * POST /api/windows/{window}/serve — complete the window's current ticket.
     */
    public function serve(ServeRequest $request): JsonResponse
    {
        $ticket = $this->routingService->serve($request->window());

        if ($ticket === null) {
            throw new NoActiveAssignmentException;
        }

        return $this->ticketResponse($ticket);
    }

    /**
     * POST /api/windows/{window}/skip — skip the current ticket (no-show) and
     * immediately assign the next eligible one. Returns the next ticket, or
     * `{ data: null }` when the queue is now empty.
     */
    public function skip(SkipRequest $request): JsonResponse
    {
        $window = $request->window();

        if ($window->currentAssignment()->doesntExist()) {
            throw new NoActiveAssignmentException;
        }

        $next = $this->routingService->skip($window);

        if ($next === null) {
            return new JsonResponse(['data' => null], Response::HTTP_OK);
        }

        return $this->ticketResponse($next);
    }

    /**
     * POST /api/windows/{window}/recall — re-announce the current ticket. No state
     * change; the broadcast re-dispatch is a hook for task 019.
     */
    public function recall(RecallRequest $request): JsonResponse
    {
        $ticket = $this->routingService->recall($request->window());

        if ($ticket === null) {
            throw new NoActiveAssignmentException;
        }

        return $this->ticketResponse($ticket);
    }

    private function ticketResponse(QueueTicket $ticket): JsonResponse
    {
        $ticket->loadMissing(['queueGroup.office', 'service', 'user']);

        return AssignedTicketResource::make($ticket)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
