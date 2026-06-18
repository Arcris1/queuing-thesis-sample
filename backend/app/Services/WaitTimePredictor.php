<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\WaitTimeFeatures;
use App\DTOs\WaitTimePrediction;
use App\Enums\PredictionBasis;
use App\Enums\TicketStatus;
use App\Models\Office;
use App\Models\QueueGroup;
use App\Models\QueueTicket;
use App\Models\Service;
use App\Support\WaitTimeModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * The single wait-time prediction seam (plan §10, task 024). Every ETA in the
 * system flows through here: `/queue/estimate`, the `eta` field in
 * `/queue/status` + join responses, and the push-notification ETA. Sharing one
 * code path means the worked-example formula and the model/fallback choice live
 * in exactly one place.
 *
 *   estimate = (people_ahead_in_group × predicted_service_minutes)
 *              ÷ max(active_windows_serving_group, 1)
 *
 * `predicted_service_minutes` comes from the trained regression when a model
 * artifact exists, else from the service's historical/seeded average, else from
 * the configured per-person placeholder (`avg_service_minutes`). The result is
 * always usable — the app never shows a blank ETA.
 *
 * The loaded model is memoized per request so a poll-heavy endpoint reads the
 * artifact at most once.
 */
final class WaitTimePredictor
{
    private bool $modelLoaded = false;

    private ?WaitTimeModel $model = null;

    /**
     * Predict the full window-aware estimate for a ticket. Assembles the live
     * features (people-ahead in the group, the service avg duration, active
     * windows serving the group, office, current hour/day), runs the model (or
     * fallback), and returns a {@see WaitTimePrediction}.
     */
    public function predictForTicket(QueueTicket $ticket, int $peopleAhead): WaitTimePrediction
    {
        $ticket->loadMissing(['queueGroup.office', 'service']);

        /** @var QueueGroup $group */
        $group = $ticket->queueGroup;
        /** @var Office $office */
        $office = $group->office;
        /** @var Service $service */
        $service = $ticket->service;

        $activeWindows = $this->activeWindowsServing($group);

        $features = new WaitTimeFeatures(
            officeId: (int) $office->id,
            queueGroupId: (int) $group->id,
            serviceId: (int) $service->id,
            avgServiceMinutes: (float) $service->avg_service_minutes,
            hourOfDay: (int) Carbon::now()->hour,
            dayOfWeek: (int) Carbon::now()->dayOfWeek,
            activeWindows: $activeWindows,
            peopleAhead: $peopleAhead,
        );

        return $this->predict($features);
    }

    /**
     * Predict from an explicit feature context. Selects the model basis, computes
     * the per-context service minutes, scales by people-ahead, divides by the
     * active windows serving the group, and derives the confidence.
     */
    public function predict(WaitTimeFeatures $features): WaitTimePrediction
    {
        $model = $this->model();

        if ($model !== null) {
            $serviceMinutes = $model->predictServiceMinutes($features);
            $basis = PredictionBasis::Model;
            $confidence = $this->confidenceFromModel($model);
            $version = $model->version;
            $trainedAt = $model->trainedAt;
        } else {
            $serviceMinutes = $this->fallbackServiceMinutes($features);
            $basis = PredictionBasis::Fallback;
            $confidence = (float) config('queue_system.prediction.confidence.fallback');
            $version = null;
            $trainedAt = null;
        }

        $windows = max(1, $features->activeWindows);
        $estimate = (int) round(($features->peopleAhead * $serviceMinutes) / $windows);

        return new WaitTimePrediction(
            estimatedMinutes: max(0, $estimate),
            predictedServiceMinutes: round($serviceMinutes, 2),
            confidence: round($confidence, 2),
            basis: $basis,
            peopleAhead: $features->peopleAhead,
            activeWindows: $features->activeWindows,
            modelVersion: $version,
            trainedAt: $trainedAt,
        );
    }

    /**
     * The number of windows currently OPEN that are attached to the ticket's queue
     * group — the divisor in the worked example. A shared group served by more
     * open windows clears faster (plan §10).
     */
    public function activeWindowsServing(QueueGroup $group): int
    {
        return $group->windows()
            ->where('windows.status', \App\Enums\WindowStatus::Open)
            ->count();
    }

    /**
     * Fallback per-context service minutes when no trained model exists: the
     * service's stored avg_service_minutes (which the seeder/training keeps in
     * sync with reality), floored at the configured minimum.
     */
    private function fallbackServiceMinutes(WaitTimeFeatures $features): float
    {
        $min = (float) config('queue_system.prediction.min_duration_minutes');

        return max($min, $features->avgServiceMinutes);
    }

    /**
     * Map the model's holdout R² onto a confidence in [min, 1.0]. R² ≤ r2_floor
     * reports the configured minimum (a model is never reported as 0% sure); R² of
     * 1.0 reports full confidence; in between it scales linearly. Documented in
     * config/queue_system.php (prediction.confidence).
     */
    private function confidenceFromModel(WaitTimeModel $model): float
    {
        if ($model->kind === WaitTimeModel::KIND_FALLBACK) {
            return (float) config('queue_system.prediction.confidence.fallback');
        }

        $floor = (float) config('queue_system.prediction.confidence.r2_floor');
        $min = (float) config('queue_system.prediction.confidence.min');

        $r2 = max(0.0, min(1.0, $model->rSquared));

        if ($r2 <= $floor) {
            return $min;
        }

        // Linearly scale (floor, 1.0] of R² onto [min, 1.0] of confidence.
        $scaled = $min + ($r2 - $floor) / (1.0 - $floor) * (1.0 - $min);

        return min(1.0, max($min, $scaled));
    }

    /**
     * Load + memoize the trained model artifact, or null when none exists yet
     * (cold-start before the first `ml:train`). Reads at most once per request.
     */
    private function model(): ?WaitTimeModel
    {
        if ($this->modelLoaded) {
            return $this->model;
        }

        $this->modelLoaded = true;
        $path = (string) config('queue_system.prediction.model_path');
        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            return $this->model = null;
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode((string) $disk->get($path), true);

        if (! is_array($data)) {
            return $this->model = null;
        }

        return $this->model = WaitTimeModel::fromArray($data);
    }

    /**
     * Whether a ticket is in a state where an ETA is meaningful (still waiting in
     * line). Serving/terminal tickets get a 0 / null ETA at the call site.
     */
    public function hasMeaningfulEta(QueueTicket $ticket): bool
    {
        return in_array($ticket->status, [TicketStatus::Waiting, TicketStatus::Ready, TicketStatus::Standby], true);
    }
}
