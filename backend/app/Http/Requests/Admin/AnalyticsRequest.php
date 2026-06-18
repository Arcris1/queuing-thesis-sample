<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\DTOs\AnalyticsFilter;

/**
 * Validates the analytics query (task 025, plan §12):
 * `GET /api/admin/analytics?office_id=&from=&to=`. Staff or admin only via
 * {@see AdminReadRequest}. All filters are optional; the
 * {@see AnalyticsFilter} is built from the validated input.
 */
final class AnalyticsRequest extends AdminReadRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'office_id' => ['sometimes', 'integer', 'exists:offices,id'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'office_id.exists' => 'That office does not exist.',
            'to.after_or_equal' => 'The "to" date must be on or after the "from" date.',
        ];
    }
}
