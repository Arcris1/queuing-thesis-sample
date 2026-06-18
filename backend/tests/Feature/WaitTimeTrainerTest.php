<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Office;
use App\Models\QueueGroup;
use App\Models\Service;
use App\Models\ServiceHistory;
use App\Services\WaitTimeTrainer;
use App\Support\WaitTimeModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Task 023: ml:train fits a regression from service_history, recovers the
 * underlying signal on a clean fixture, and persists the JSON artifact. Below
 * the cold-start floor it stores a per-service-average fallback instead.
 */
class WaitTimeTrainerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config()->set('queue_system.prediction.min_training_rows', 50);
    }

    private function context(): Service
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create();

        return Service::factory()->forQueueGroup($group)->create(['avg_service_minutes' => 5]);
    }

    public function test_train_recovers_a_clean_linear_signal_and_writes_the_artifact(): void
    {
        $service = $this->context();

        // duration = 2 + 0.5·active_windows·? — build a clean, learnable signal:
        // duration = 4 + 0.5·hour - 0.3·active_windows (deterministic, no noise).
        for ($i = 0; $i < 200; $i++) {
            $hour = $i % 12 + 8;       // 8..19
            $windows = $i % 3 + 1;     // 1..3
            $duration = 4 + 0.5 * $hour - 0.3 * $windows;

            ServiceHistory::factory()->create([
                'office_id' => $service->office_id,
                'queue_group_id' => $service->queue_group_id,
                'service_id' => $service->id,
                'duration_minutes' => round($duration, 2),
                'hour_of_day' => $hour,
                'day_of_week' => $i % 7,
                'active_windows' => $windows,
            ]);
        }

        $model = app(WaitTimeTrainer::class)->train();

        $this->assertSame(WaitTimeModel::KIND_LINEAR, $model->kind);
        $this->assertSame(200, $model->trainingRows);
        // A clean signal should be fit very well on the holdout.
        $this->assertGreaterThan(0.95, $model->rSquared);
        $this->assertLessThan(1.0, $model->rmse);

        // Artifact persisted and reloadable.
        $path = (string) config('queue_system.prediction.model_path');
        Storage::disk('local')->assertExists($path);

        /** @var array<string, mixed> $json */
        $json = json_decode((string) Storage::disk('local')->get($path), true);
        $reloaded = WaitTimeModel::fromArray($json);

        $this->assertSame(WaitTimeModel::KIND_LINEAR, $reloaded->kind);
        $this->assertSame($model->coefficients, $reloaded->coefficients);
    }

    public function test_cold_start_stores_a_fallback_model_with_service_averages(): void
    {
        $service = $this->context();

        // Below the 50-row floor → fallback. Two rows averaging 6 minutes.
        ServiceHistory::factory()->create([
            'office_id' => $service->office_id,
            'queue_group_id' => $service->queue_group_id,
            'service_id' => $service->id,
            'duration_minutes' => 4,
        ]);
        ServiceHistory::factory()->create([
            'office_id' => $service->office_id,
            'queue_group_id' => $service->queue_group_id,
            'service_id' => $service->id,
            'duration_minutes' => 8,
        ]);

        $model = app(WaitTimeTrainer::class)->train();

        $this->assertSame(WaitTimeModel::KIND_FALLBACK, $model->kind);
        $this->assertSame(2, $model->trainingRows);
        $this->assertEqualsWithDelta(6.0, $model->serviceAverages[$service->id], 1e-9);
    }

    public function test_ml_train_command_prints_metrics(): void
    {
        $service = $this->context();
        ServiceHistory::factory()->create([
            'office_id' => $service->office_id,
            'queue_group_id' => $service->queue_group_id,
            'service_id' => $service->id,
        ]);

        $this->artisan('ml:train')
            ->expectsOutputToContain('Training wait-time model')
            ->assertSuccessful();
    }
}
