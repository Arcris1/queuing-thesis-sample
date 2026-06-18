<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\QueueUpdated;
use App\Exceptions\CrossOfficeAttachException;
use App\Exceptions\QueueGroupNotAttachedException;
use App\Models\QueueGroup;
use App\Models\Window;
use Illuminate\Support\Facades\DB;

/**
 * Dynamic window enabling (research contribution #1, plan §5.4 — "no idle
 * windows"). Attaching/detaching a queue group to/from a window is purely a
 * `window_queue_groups` pivot change: because {@see RoutingService::assignNext()}
 * reads that pivot live, a newly attached group is served on the window's very
 * next available-call with no code change, and detaching reverts the capability
 * without disturbing any in-flight assignment.
 *
 * Both operations run in a transaction and broadcast a board refresh after commit
 * so the live board (task 025 / 036) reflects the change without a manual reload.
 */
final class WindowQueueGroupService
{
    /**
     * Attach a queue group to a window (task 043).
     *
     * Idempotent: re-attaching a group the window already serves is a no-op, not an
     * error, so a double-submit never 500s. The group must belong to the window's
     * office — a cross-office attach raises a self-rendering 422
     * ({@see CrossOfficeAttachException}) so a window can never be widened to serve
     * another office's line.
     *
     * Returns the window with its refreshed queue-group set eager-loaded.
     */
    public function attach(Window $window, QueueGroup $group): Window
    {
        if ($group->office_id !== $window->office_id) {
            throw new CrossOfficeAttachException;
        }

        DB::transaction(function () use ($window, $group): void {
            // syncWithoutDetaching keeps the attach idempotent: an already-attached
            // group is left untouched rather than duplicated or rejected.
            $window->queueGroups()->syncWithoutDetaching([$group->id]);
        });

        // After commit (task 019): the window's capability changed → repaint the
        // affected group's board summary so dashboards reflect the new coverage.
        event(QueueUpdated::forGroup($group));

        return $this->withGroups($window);
    }

    /**
     * Detach a queue group from a window (task 043).
     *
     * A group that is not attached is a 404 ({@see QueueGroupNotAttachedException}).
     * Detaching the window's last group is intentionally ALLOWED (plan §5.4 default
     * — an admin may fully un-assign a window before closing/reassigning it); flip
     * this to a guard here if a "≥1 group" business rule is later required. This
     * changes capability only — any in-flight assignment on the window is untouched
     * and still completes via serve/skip.
     *
     * Returns the window with its refreshed queue-group set eager-loaded.
     */
    public function detach(Window $window, QueueGroup $group): Window
    {
        $isAttached = $window->queueGroups()
            ->whereKey($group->id)
            ->exists();

        if (! $isAttached) {
            throw new QueueGroupNotAttachedException;
        }

        DB::transaction(function () use ($window, $group): void {
            $window->queueGroups()->detach($group->id);
        });

        // After commit (task 019): the window no longer serves this group → refresh
        // its board summary.
        event(QueueUpdated::forGroup($group));

        return $this->withGroups($window);
    }

    /**
     * Reload the window's queue-group pivot fresh from the database so the returned
     * resource reflects the post-mutation state exactly.
     */
    private function withGroups(Window $window): Window
    {
        return $window->load('queueGroups');
    }
}
