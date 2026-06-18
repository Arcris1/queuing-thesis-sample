<?php

declare(strict_types=1);

namespace App\Support;

use App\DTOs\WaitTimeFeatures;

/**
 * The trained wait-time artifact + its feature encoding (plan §10, task 023).
 *
 * Bundling the learned coefficients WITH the encoding metadata in one object is
 * what makes train-time and inference-time encoding provably identical: the
 * exact category vocabularies (office / queue_group / service ids seen in
 * training) are persisted, so {@see encode()} produces the same design vector
 * during training and during a live `/queue/estimate` call. Unknown categories
 * at inference simply leave their one-hot slot at 0 (the model falls back to the
 * intercept + numeric terms for that dimension).
 *
 * Design vector layout (fixed order):
 *   [ 1 (intercept),
 *     avg_service_minutes, hour_of_day, day_of_week, active_windows,
 *     one-hot(office_id…), one-hot(queue_group_id…), one-hot(service_id…) ]
 *
 * Two model kinds:
 *   - 'linear'   — full regression: predict via the coefficient dot product.
 *   - 'fallback' — cold-start: no regression; predict the per-service average
 *                  duration (or the global average for an unseen service).
 */
final class WaitTimeModel
{
    public const KIND_LINEAR = 'linear';

    public const KIND_FALLBACK = 'fallback';

    /**
     * @param  array<int, float>   $coefficients     aligned to the design-vector layout
     * @param  array<int, int>     $offices          office_id vocabulary (one-hot order)
     * @param  array<int, int>     $queueGroups      queue_group_id vocabulary
     * @param  array<int, int>     $services         service_id vocabulary
     * @param  array<int, float>   $serviceAverages  service_id => mean duration (fallback)
     */
    public function __construct(
        public readonly string $kind,
        public readonly array $coefficients,
        public readonly array $offices,
        public readonly array $queueGroups,
        public readonly array $services,
        public readonly array $serviceAverages,
        public readonly float $globalAverage,
        public readonly float $rSquared,
        public readonly float $rmse,
        public readonly int $trainingRows,
        public readonly string $version,
        public readonly string $trainedAt,
    ) {}

    /**
     * Predict the per-context SERVICE duration (minutes) for the given features.
     * Linear models use the regression; fallback models use the per-service mean.
     * The result is floored at 1 minute so a degenerate prediction never yields a
     * zero or negative service time.
     */
    public function predictServiceMinutes(WaitTimeFeatures $features): float
    {
        if ($this->kind === self::KIND_FALLBACK) {
            $minutes = $this->serviceAverages[$features->serviceId] ?? $this->globalAverage;

            return max(1.0, $minutes);
        }

        $row = $this->encode($features);
        $minutes = LinearRegression::predict($row, $this->coefficients);

        return max(1.0, $minutes);
    }

    /**
     * Encode a feature context into the fixed-order design vector. Used by BOTH
     * training (over historical rows) and inference (over a live ticket), which
     * guarantees consistent encoding.
     *
     * @return array<int, float>
     */
    public function encode(WaitTimeFeatures $features): array
    {
        $row = [
            1.0, // intercept
            $features->avgServiceMinutes,
            (float) $features->hourOfDay,
            (float) $features->dayOfWeek,
            (float) $features->activeWindows,
        ];

        foreach ($this->offices as $officeId) {
            $row[] = $officeId === $features->officeId ? 1.0 : 0.0;
        }

        foreach ($this->queueGroups as $groupId) {
            $row[] = $groupId === $features->queueGroupId ? 1.0 : 0.0;
        }

        foreach ($this->services as $serviceId) {
            $row[] = $serviceId === $features->serviceId ? 1.0 : 0.0;
        }

        return $row;
    }

    /**
     * The number of columns in the design vector for this model's vocabularies.
     */
    public function featureCount(): int
    {
        return 5 + count($this->offices) + count($this->queueGroups) + count($this->services);
    }

    /**
     * Serialize to the JSON-storable artifact shape (task 023 storage format).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'version' => $this->version,
            'trained_at' => $this->trainedAt,
            'training_rows' => $this->trainingRows,
            'metrics' => [
                'r_squared' => $this->rSquared,
                'rmse' => $this->rmse,
            ],
            'features' => [
                'numeric' => ['avg_service_minutes', 'hour_of_day', 'day_of_week', 'active_windows'],
                'offices' => $this->offices,
                'queue_groups' => $this->queueGroups,
                'services' => $this->services,
            ],
            'coefficients' => $this->coefficients,
            'service_averages' => $this->serviceAverages,
            'global_average' => $this->globalAverage,
        ];
    }

    /**
     * Rehydrate a model from its stored JSON artifact.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed> $features */
        $features = $data['features'] ?? [];
        /** @var array<string, mixed> $metrics */
        $metrics = $data['metrics'] ?? [];

        /** @var array<int, float> $serviceAverages */
        $serviceAverages = [];
        foreach (($data['service_averages'] ?? []) as $serviceId => $avg) {
            $serviceAverages[(int) $serviceId] = (float) $avg;
        }

        return new self(
            kind: (string) ($data['kind'] ?? self::KIND_FALLBACK),
            coefficients: array_map('floatval', $data['coefficients'] ?? []),
            offices: array_map('intval', $features['offices'] ?? []),
            queueGroups: array_map('intval', $features['queue_groups'] ?? []),
            services: array_map('intval', $features['services'] ?? []),
            serviceAverages: $serviceAverages,
            globalAverage: (float) ($data['global_average'] ?? 1.0),
            rSquared: (float) ($metrics['r_squared'] ?? 0.0),
            rmse: (float) ($metrics['rmse'] ?? 0.0),
            trainingRows: (int) ($data['training_rows'] ?? 0),
            version: (string) ($data['version'] ?? 'unknown'),
            trainedAt: (string) ($data['trained_at'] ?? ''),
        );
    }
}
