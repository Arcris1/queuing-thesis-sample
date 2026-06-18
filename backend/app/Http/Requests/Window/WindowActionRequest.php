<?php

declare(strict_types=1);

namespace App\Http\Requests\Window;

use App\Enums\Role;
use App\Models\User;
use App\Models\Window;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base authorization for all window/staff control actions (task 021).
 *
 * Only Staff and Admin may operate a window. The route-bound {@see Window} is
 * resolved here so concrete requests (available/serve/skip/recall) inherit the
 * staff gate. A failed authorize() yields 403 via the framework.
 *
 * NOTE: office-scoped authorization (staff may only operate windows in their own
 * office) is a documented extension point — the User model does not yet carry an
 * office assignment, so we gate on role only for now. Tighten here once staff
 * carry an `office_id` (plan §7 staff/window policy).
 */
abstract class WindowActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user('api');

        if ($user === null) {
            return false;
        }

        return in_array($user->role, [Role::Staff, Role::Admin], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * The window this action targets, resolved from the route key. Resolved here
     * (rather than relying on implicit binding) so the staff gate in authorize()
     * and the controller share a single Window instance.
     */
    public function window(): Window
    {
        $route = $this->route('window');

        if ($route instanceof Window) {
            return $route;
        }

        $this->resolvedWindow ??= Window::query()->findOrFail($route);

        return $this->resolvedWindow;
    }

    private ?Window $resolvedWindow = null;
}
