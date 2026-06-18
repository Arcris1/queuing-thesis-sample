<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\QueueGroupStatus;
use App\Models\Office;
use Illuminate\Database\Eloquent\Collection;

final class CatalogService
{
    /**
     * All offices for the catalog list screen.
     *
     * @return Collection<int, Office>
     */
    public function offices(): Collection
    {
        return Office::query()
            ->orderBy('name')
            ->get();
    }

    /**
     * The office's open queue groups, each with its services, shaped for the
     * mobile join screen (Office -> Queue Group -> Service). Eager-loaded to
     * avoid N+1.
     */
    public function officeWithServices(Office $office): Office
    {
        return $office->load([
            'queueGroups' => fn ($query) => $query
                ->where('status', QueueGroupStatus::Open)
                ->orderBy('name'),
            'queueGroups.services' => fn ($query) => $query->orderBy('name'),
        ]);
    }
}
