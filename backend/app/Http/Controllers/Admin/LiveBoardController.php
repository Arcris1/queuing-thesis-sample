<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LiveBoardRequest;
use App\Http\Resources\LiveBoardResource;
use App\Services\LiveBoardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * The dashboard live-board read (task 025, plan §5 / §7 / §12). Thin: staff/admin
 * gate via the Form Request, delegate the snapshot assembly to
 * {@see LiveBoardService}, return a {@see LiveBoardResource} in the standard
 * `{ data: ... }` envelope. Clients fetch this once then live-update via Reverb.
 */
final class LiveBoardController extends Controller
{
    public function __construct(
        private readonly LiveBoardService $board,
    ) {}

    /**
     * GET /api/admin/queue/{office}/live — per-queue-group lines (now-serving,
     * counts, ordered waiting tickets with presence + ETA) and per-window state
     * (status, served groups, current assignment) for one office.
     */
    public function show(LiveBoardRequest $request): JsonResponse
    {
        $board = $this->board->forOffice($request->office());

        return LiveBoardResource::make($board)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
