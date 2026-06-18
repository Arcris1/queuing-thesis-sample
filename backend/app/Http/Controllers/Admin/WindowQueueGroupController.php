<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AttachQueueGroupRequest;
use App\Http\Requests\Admin\DetachQueueGroupRequest;
use App\Http\Resources\WindowStateResource;
use App\Models\Window;
use App\Services\WindowQueueGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Admin dynamic window enabling (task 043, plan §5.4 / §7). Thin: each action is
 * authorized admin-only by its Form Request, delegates the pivot change to
 * {@see WindowQueueGroupService}, and returns the updated window state (its name,
 * status, and the queue groups it now serves) in the `{ data: ... }` envelope.
 */
final class WindowQueueGroupController extends Controller
{
    public function __construct(
        private readonly WindowQueueGroupService $service,
    ) {}

    /**
     * POST /api/admin/windows/{window}/queue-groups — attach a queue group so the
     * routing engine immediately widens what this window can be assigned (§5.4).
     */
    public function store(AttachQueueGroupRequest $request): JsonResponse
    {
        $window = $this->service->attach($request->window(), $request->queueGroup());

        return $this->windowResponse($window);
    }

    /**
     * DELETE /api/admin/windows/{window}/queue-groups/{queueGroup} — detach a queue
     * group; capability narrows, in-flight assignments are unaffected.
     */
    public function destroy(DetachQueueGroupRequest $request): JsonResponse
    {
        $window = $this->service->detach($request->window(), $request->queueGroup());

        return $this->windowResponse($window);
    }

    private function windowResponse(Window $window): JsonResponse
    {
        $window->loadMissing(['queueGroups', 'currentAssignment.ticket.queueGroup.office',
            'currentAssignment.ticket.service', 'currentAssignment.ticket.user']);

        return WindowStateResource::make($window)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
