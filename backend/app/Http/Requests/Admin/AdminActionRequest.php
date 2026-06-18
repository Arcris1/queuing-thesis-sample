<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Http\Requests\Window\WindowActionRequest;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base authorization for admin-only mutations (task 043 — dynamic queue-group
 * attach/detach, plan §5.4 / §7).
 *
 * Unlike {@see WindowActionRequest} (which admits both
 * Staff and Admin), reconfiguring a window's capability — widening/narrowing what
 * it can be assigned — is an administrative act, so only Admin passes here. A
 * failed authorize() yields 403 via the framework.
 *
 * NOTE: office-scoped authorization (an admin may only reconfigure windows in
 * their own office) is a documented extension point — the User model does not yet
 * carry an office assignment, so we gate on role only for now (plan §7).
 */
abstract class AdminActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user('api');

        if ($user === null) {
            return false;
        }

        return $user->role === Role::Admin;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
