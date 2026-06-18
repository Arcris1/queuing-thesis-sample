<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\AnalyticsFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnalyticsRequest;
use App\Http\Resources\AnalyticsResource;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * The dashboard analytics read (task 025, plan §12). Thin: staff/admin gate via
 * the Form Request, build the {@see AnalyticsFilter} DTO from the validated query,
 * delegate aggregation to {@see AnalyticsService}, return an
 * {@see AnalyticsResource} in the `{ data: ... }` envelope.
 */
final class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analytics,
    ) {}

    /**
     * GET /api/admin/analytics?office_id=&from=&to= — averages and aggregates over
     * service_history + tickets + window_assignments for the filter context.
     */
    public function index(AnalyticsRequest $request): JsonResponse
    {
        $result = $this->analytics->compute(AnalyticsFilter::fromRequest($request));

        return AnalyticsResource::make($result)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
