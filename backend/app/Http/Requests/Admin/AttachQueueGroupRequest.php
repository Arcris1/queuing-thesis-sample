<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\QueueGroup;
use App\Models\Window;

/**
 * Validates an admin's request to attach a queue group to a window (task 043,
 * plan §5.4 / §7): `POST /api/admin/windows/{window}/queue-groups` with body
 * `{ queue_group_id }`. Admin-only via {@see AdminActionRequest}.
 *
 * The same-office rule is enforced in the service (it raises a self-rendering 422)
 * rather than here, so the controller has a single resolved {@see QueueGroup}
 * instance and the cross-office decision lives next to the attach transaction.
 */
final class AttachQueueGroupRequest extends AdminActionRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'queue_group_id' => ['required', 'integer', 'exists:queue_groups,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'queue_group_id.required' => 'A queue group is required.',
            'queue_group_id.exists' => 'That queue group does not exist.',
        ];
    }

    /**
     * The window this attach targets, resolved from the route key.
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
     * The queue group to attach, resolved from the validated body.
     */
    public function queueGroup(): QueueGroup
    {
        return $this->resolvedGroup ??= QueueGroup::query()
            ->findOrFail((int) $this->validated('queue_group_id'));
    }

    private ?Window $resolvedWindow = null;

    private ?QueueGroup $resolvedGroup = null;
}
