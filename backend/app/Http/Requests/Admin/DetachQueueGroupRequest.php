<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\QueueGroup;
use App\Models\Window;

/**
 * Validates an admin's request to detach a queue group from a window (task 043,
 * plan §5.4 / §7): `DELETE /api/admin/windows/{window}/queue-groups/{queueGroup}`.
 * Admin-only via {@see AdminActionRequest}.
 *
 * Both models come from the route. A group that is not actually attached yields a
 * self-rendering 404 in the service.
 */
final class DetachQueueGroupRequest extends AdminActionRequest
{
    /**
     * The window to detach from, resolved from the route key.
     */
    public function window(): Window
    {
        $route = $this->route('window');

        if ($route instanceof Window) {
            return $route;
        }

        return $this->resolvedWindow ??= Window::query()->findOrFail($route);
    }

    /**
     * The queue group to detach, resolved from the route key.
     */
    public function queueGroup(): QueueGroup
    {
        $route = $this->route('queueGroup');

        if ($route instanceof QueueGroup) {
            return $route;
        }

        return $this->resolvedGroup ??= QueueGroup::query()->findOrFail($route);
    }

    private ?Window $resolvedWindow = null;

    private ?QueueGroup $resolvedGroup = null;
}
