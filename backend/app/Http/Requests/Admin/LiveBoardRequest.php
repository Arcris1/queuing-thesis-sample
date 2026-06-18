<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Office;

/**
 * Authorizes the live-board read (task 025): `GET /api/admin/queue/{office}/live`.
 * Staff or admin only via {@see AdminReadRequest}. No body — the office comes from
 * the route.
 */
final class LiveBoardRequest extends AdminReadRequest
{
    /**
     * The office whose board is requested, resolved from the route key.
     */
    public function office(): Office
    {
        $route = $this->route('office');

        if ($route instanceof Office) {
            return $route;
        }

        return $this->resolvedOffice ??= Office::query()->findOrFail($route);
    }

    private ?Office $resolvedOffice = null;
}
