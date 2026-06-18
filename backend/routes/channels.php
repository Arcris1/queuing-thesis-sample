<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\QueueGroup;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast channel authorization (tasks 018 / 019)
|--------------------------------------------------------------------------
|
| Authorization runs over the JWT "api" guard — the broadcasting/auth route is
| registered with `auth:api` in bootstrap/app.php, so $user here is the
| token-authenticated student/staff. Return truthy to authorize a subscription.
|
| --------------------------------------------------------------------------
| CLIENT SUBSCRIPTION CONTRACT (keep in sync with the 019 task file + clients)
| --------------------------------------------------------------------------
|
| Private channel  `user.{id}`            — a student's personal channel.
|   Authorized when Auth::id() === {id}. Events:
|     - `ticket.called`  (App\Events\TicketCalled)  payload: see broadcastWith()
|
| Private channel  `queue-group.{id}`     — a queue group's live board.
|   Authorized for staff/admin only (display surface for the dashboard). Events:
|     - `ticket.called`     (App\Events\TicketCalled)
|     - `queue.updated`     (App\Events\QueueUpdated)
|     - `presence.changed`  (App\Events\PresenceChanged)
|
| Private channel  `office.{id}`          — an office-wide board (all groups).
|   Authorized for staff/admin only. Events:
|     - `ticket.called`     (App\Events\TicketCalled)
|     - `queue.updated`     (App\Events\QueueUpdated)
|     - `presence.changed`  (App\Events\PresenceChanged)
|
| The PUBLIC "now serving" board has no socket: the mobile/web display polls the
| unauthenticated REST endpoint GET /api/queue/current. Sockets are reserved for
| authenticated student (personal) and staff (board) surfaces.
|
*/

/**
 * A student's personal channel — only the owner may subscribe. Carries the
 * "you're being called to Window X" event (task 019).
 */
Broadcast::channel('user.{id}', static function (User $user, int $id): bool {
    return $user->id === $id;
});

/**
 * A queue group's live board (staff dashboard). Staff/admin only — students
 * watch their own `user.{id}` channel, not the board.
 */
Broadcast::channel('queue-group.{queueGroup}', static function (User $user, int $queueGroup): bool {
    if (! in_array($user->role, [Role::Staff, Role::Admin], true)) {
        return false;
    }

    return QueueGroup::query()->whereKey($queueGroup)->exists();
});

/**
 * An office-wide board (all of that office's queue groups). Staff/admin only.
 */
Broadcast::channel('office.{office}', static function (User $user, int $office): bool {
    return in_array($user->role, [Role::Staff, Role::Admin], true);
});
