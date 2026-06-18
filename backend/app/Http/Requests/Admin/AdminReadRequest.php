<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Http\Requests\Window\WindowActionRequest;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base authorization for the dashboard read endpoints (task 025 — live board +
 * analytics, plan §7 / §12).
 *
 * These are reporting/monitoring reads consumed by both the staff live board and
 * the admin analytics views, so — like the window controls
 * ({@see WindowActionRequest}) — both Staff and Admin
 * are admitted. Students are forbidden (403). The admin-only mutations (task 043)
 * use {@see AdminActionRequest} instead.
 */
abstract class AdminReadRequest extends FormRequest
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
}
