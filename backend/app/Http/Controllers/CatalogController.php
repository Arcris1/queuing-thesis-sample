<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\OfficeResource;
use App\Http\Resources\OfficeServicesResource;
use App\Models\Office;
use App\Services\CatalogService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CatalogController extends Controller
{
    public function __construct(
        private readonly CatalogService $catalogService,
    ) {}

    public function offices(): AnonymousResourceCollection
    {
        return OfficeResource::collection($this->catalogService->offices());
    }

    public function services(Office $office): OfficeServicesResource
    {
        return OfficeServicesResource::make(
            $this->catalogService->officeWithServices($office),
        );
    }
}
