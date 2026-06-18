<?php

declare(strict_types=1);

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * The validated filter context for an analytics query (task 025, plan §12):
 * optional office scope and an optional [from, to] date range applied to
 * `service_history.served_at` and tickets' `joined_at`. Built from the request via
 * {@see fromRequest()} so the controller never threads raw query params into the
 * service.
 */
final readonly class AnalyticsFilter
{
    public function __construct(
        public ?int $officeId,
        public ?Carbon $from,
        public ?Carbon $to,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        /** @var int|null $officeId */
        $officeId = $request->filled('office_id') ? (int) $request->validated('office_id') : null;

        $from = $request->filled('from')
            ? Carbon::parse((string) $request->validated('from'))->startOfDay()
            : null;

        $to = $request->filled('to')
            ? Carbon::parse((string) $request->validated('to'))->endOfDay()
            : null;

        return new self(
            officeId: $officeId,
            from: $from,
            to: $to,
        );
    }
}
