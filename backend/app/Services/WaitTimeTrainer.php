<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\WaitTimeFeatures;
use App\Models\Service;
use App\Models\ServiceHistory;
use App\Support\LinearRegression;
use App\Support\WaitTimeModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Trains the wait-time regression from `service_history` and persists the
 * artifact the {@see WaitTimePredictor} loads (plan §10, task 023).
 *
 * Self-contained in PHP — NO Python microservice. The target is the per-row
 * SERVICE duration (minutes); features are the avg service duration, hour, day,
 * active windows (numeric) plus one-hot office / queue_group / service. Training
 * is reproducible (a deterministic, seeded shuffle for the holdout split) and
 * degrades to a per-service-average fallback when history is too thin
 * (cold-start, plan §10).
 */
final class WaitTimeTrainer
{
    /**
     * Fit a model from all of `service_history`, evaluate it on a holdout, persist
     * the JSON artifact, and return the trained {@see WaitTimeModel}.
     */
    public function train(): WaitTimeModel
    {
        /** @var Collection<int, ServiceHistory> $rows */
        $rows = ServiceHistory::query()
            ->orderBy('id')
            ->get(['office_id', 'queue_group_id', 'service_id', 'duration_minutes', 'hour_of_day', 'day_of_week', 'active_windows']);

        $model = $this->fit($rows);

        $this->persist($model);

        return $model;
    }

    /**
     * Build a model from in-memory history rows (no persistence). Exposed for
     * testing the math on a deterministic fixture (task 023).
     *
     * @param  Collection<int, ServiceHistory>  $rows
     */
    public function fit(Collection $rows): WaitTimeModel
    {
        $minRows = (int) config('queue_system.prediction.min_training_rows');
        $serviceAverages = $this->serviceAverages($rows);
        $globalAverage = $rows->count() > 0
            ? (float) $rows->avg(fn (ServiceHistory $r): float => (float) $r->duration_minutes)
            : (float) config('queue_system.prediction.min_duration_minutes');

        $now = Carbon::now();
        $version = 'v'.$now->format('YmdHis');
        $trainedAt = $now->toIso8601String();

        // Cold-start: too little history → store the per-service-average fallback.
        if ($rows->count() < $minRows) {
            return new WaitTimeModel(
                kind: WaitTimeModel::KIND_FALLBACK,
                coefficients: [],
                offices: [],
                queueGroups: [],
                services: [],
                serviceAverages: $serviceAverages,
                globalAverage: max(1.0, $globalAverage),
                rSquared: 0.0,
                rmse: 0.0,
                trainingRows: $rows->count(),
                version: $version,
                trainedAt: $trainedAt,
            );
        }

        // Build the category vocabularies from the full dataset so the encoding is
        // stable across the train/holdout split AND at inference.
        $offices = $rows->pluck('office_id')->unique()->sort()->values()->map(fn ($v): int => (int) $v)->all();
        $queueGroups = $rows->pluck('queue_group_id')->unique()->sort()->values()->map(fn ($v): int => (int) $v)->all();
        $services = $rows->pluck('service_id')->unique()->sort()->values()->map(fn ($v): int => (int) $v)->all();

        // Deterministic shuffle (fixed seed) so the holdout split is reproducible.
        $ordered = $rows->values();
        $indices = range(0, $ordered->count() - 1);
        mt_srand(2026);
        shuffle($indices);
        mt_srand();

        $holdoutFraction = (float) config('queue_system.prediction.holdout_fraction');
        $holdoutCount = (int) floor($ordered->count() * $holdoutFraction);
        $holdoutIndices = array_flip(array_slice($indices, 0, $holdoutCount));

        // A scaffold model used only for its encode() — coefficients filled after fit.
        $scaffold = new WaitTimeModel(
            kind: WaitTimeModel::KIND_LINEAR,
            coefficients: [],
            offices: $offices,
            queueGroups: $queueGroups,
            services: $services,
            serviceAverages: $serviceAverages,
            globalAverage: max(1.0, $globalAverage),
            rSquared: 0.0,
            rmse: 0.0,
            trainingRows: $ordered->count(),
            version: $version,
            trainedAt: $trainedAt,
        );

        $trainX = [];
        $trainY = [];
        $holdoutX = [];
        $holdoutY = [];

        foreach ($ordered as $i => $row) {
            $features = $this->featuresFromHistory($row);
            $design = $scaffold->encode($features);
            $target = (float) $row->duration_minutes;

            if (isset($holdoutIndices[$i])) {
                $holdoutX[] = $design;
                $holdoutY[] = $target;
            } else {
                $trainX[] = $design;
                $trainY[] = $target;
            }
        }

        // If the holdout fraction yielded an empty train set, train on everything.
        if ($trainX === []) {
            $trainX = $holdoutX;
            $trainY = $holdoutY;
        }

        $coefficients = LinearRegression::fit($trainX, $trainY);

        // Evaluate on the holdout (fall back to the train set if no holdout rows).
        $evalX = $holdoutX !== [] ? $holdoutX : $trainX;
        $evalY = $holdoutY !== [] ? $holdoutY : $trainY;
        $predicted = array_map(
            static fn (array $r): float => LinearRegression::predict($r, $coefficients),
            $evalX,
        );

        return new WaitTimeModel(
            kind: WaitTimeModel::KIND_LINEAR,
            coefficients: $coefficients,
            offices: $offices,
            queueGroups: $queueGroups,
            services: $services,
            serviceAverages: $serviceAverages,
            globalAverage: max(1.0, $globalAverage),
            rSquared: LinearRegression::rSquared($evalY, $predicted),
            rmse: LinearRegression::rmse($evalY, $predicted),
            trainingRows: $ordered->count(),
            version: $version,
            trainedAt: $trainedAt,
        );
    }

    /**
     * Persist a model to its JSON artifact on the local disk.
     */
    public function persist(WaitTimeModel $model): void
    {
        $path = (string) config('queue_system.prediction.model_path');

        Storage::disk('local')->put(
            $path,
            (string) json_encode($model->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }

    /**
     * Per-service mean duration over the dataset — the fallback model's lookup and
     * a safety net for services never fit by the regression.
     *
     * @param  Collection<int, ServiceHistory>  $rows
     * @return array<int, float>
     */
    private function serviceAverages(Collection $rows): array
    {
        return $rows
            ->groupBy('service_id')
            ->map(fn (Collection $group): float => (float) $group->avg(
                fn (ServiceHistory $r): float => (float) $r->duration_minutes,
            ))
            ->mapWithKeys(fn (float $avg, int|string $serviceId): array => [(int) $serviceId => $avg])
            ->all();
    }

    /**
     * Build the feature context from a historical row. `avg_service_minutes` comes
     * from the related Service (cached per service id to avoid an N+1).
     */
    private function featuresFromHistory(ServiceHistory $row): WaitTimeFeatures
    {
        return new WaitTimeFeatures(
            officeId: (int) $row->office_id,
            queueGroupId: (int) $row->queue_group_id,
            serviceId: (int) $row->service_id,
            avgServiceMinutes: (float) $this->avgServiceMinutes((int) $row->service_id),
            hourOfDay: (int) $row->hour_of_day,
            dayOfWeek: (int) $row->day_of_week,
            activeWindows: (int) $row->active_windows,
        );
    }

    /**
     * @var array<int, float>
     */
    private array $avgCache = [];

    private function avgServiceMinutes(int $serviceId): float
    {
        if (! isset($this->avgCache[$serviceId])) {
            /** @var Service|null $service */
            $service = Service::query()->find($serviceId, ['id', 'avg_service_minutes']);
            $this->avgCache[$serviceId] = (float) ($service?->avg_service_minutes ?? 1);
        }

        return $this->avgCache[$serviceId];
    }
}
