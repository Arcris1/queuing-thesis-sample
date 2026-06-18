<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PredictionBasis;
use App\Enums\TicketStatus;
use App\Enums\WindowStatus;
use App\Models\Office;
use App\Models\QueueGroup;
use App\Models\QueueTicket;
use App\Models\Service;
use App\Models\User;
use App\Models\Window;
use App\Support\WaitTimeModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Task 024: GET /api/queue/estimate returns the window-aware AI estimate for the
 * caller's active ticket (model path + fallback path + the (8×4)/2 worked
 * example), and 404s when there is no active ticket.
 */
class QueueEstimateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function token(User $user): string
    {
        return auth('api')->login($user);
    }

    /**
     * Build "8 ahead, avg 4 min, 2 open windows" in one shared group, returning
     * the caller's ticket. Matches the plan's worked example shape.
     *
     * @return array{0: User, 1: QueueTicket, 2: QueueGroup, 3: Service}
     */
    private function accountingGeneralScenario(int $peopleAhead = 8, int $openWindows = 2): array
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);
        $service = Service::factory()->forQueueGroup($group)->create(['avg_service_minutes' => 4]);

        // Open windows serving the group (the divisor).
        for ($i = 0; $i < $openWindows; $i++) {
            $window = Window::factory()->for($office)->create(['status' => WindowStatus::Open]);
            $window->queueGroups()->attach($group->id);
        }

        $base = ['queue_group_id' => $group->id, 'service_id' => $service->id];

        // People ahead, all joined earlier.
        for ($i = 0; $i < $peopleAhead; $i++) {
            QueueTicket::factory()->waiting()->create($base + [
                'user_id' => User::factory(),
                'joined_at' => now()->subMinutes(30 - $i),
            ]);
        }

        $user = User::factory()->create();
        $ticket = QueueTicket::factory()->waiting()->create($base + [
            'user_id' => $user->id,
            'joined_at' => now(),
            'ticket_number' => 'A-009',
        ]);

        return [$user, $ticket, $group, $service];
    }

    /**
     * Persist a deterministic FALLBACK model whose per-service average for the
     * given service is exactly $serviceMinutes, so the estimate is predictable.
     */
    private function seedFallbackModel(int $serviceId, float $serviceMinutes): void
    {
        $model = new WaitTimeModel(
            kind: WaitTimeModel::KIND_FALLBACK,
            coefficients: [],
            offices: [],
            queueGroups: [],
            services: [],
            serviceAverages: [$serviceId => $serviceMinutes],
            globalAverage: $serviceMinutes,
            rSquared: 0.8,
            rmse: 1.0,
            trainingRows: 1000,
            version: 'v-test',
            trainedAt: '2026-06-18T00:00:00+00:00',
        );

        Storage::disk('local')->put(
            (string) config('queue_system.prediction.model_path'),
            (string) json_encode($model->toArray()),
        );
    }

    public function test_estimate_uses_the_model_and_reproduces_the_worked_example(): void
    {
        [$user, $ticket, , $service] = $this->accountingGeneralScenario(peopleAhead: 8, openWindows: 2);

        // Model says this service takes 4 min ⇒ (8 × 4) ÷ 2 = 16.
        $this->seedFallbackModel($service->id, 4.0);

        $this->withHeader('Authorization', "Bearer {$this->token($user)}")
            ->getJson('/api/queue/estimate')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'estimated_minutes', 'predicted_service_minutes', 'confidence',
                    'people_ahead', 'active_windows', 'basis', 'model_version', 'trained_at',
                ],
            ])
            ->assertJsonPath('data.estimated_minutes', 16)
            ->assertJsonPath('data.people_ahead', 8)
            ->assertJsonPath('data.active_windows', 2)
            ->assertJsonPath('data.basis', PredictionBasis::Model->value)
            ->assertJsonPath('data.model_version', 'v-test');
    }

    public function test_estimate_divides_by_active_windows(): void
    {
        // Same 8 ahead × 4 min but only ONE open window ⇒ 32, not 16.
        [$user, , , $service] = $this->accountingGeneralScenario(peopleAhead: 8, openWindows: 1);
        $this->seedFallbackModel($service->id, 4.0);

        $this->withHeader('Authorization', "Bearer {$this->token($user)}")
            ->getJson('/api/queue/estimate')
            ->assertOk()
            ->assertJsonPath('data.active_windows', 1)
            ->assertJsonPath('data.estimated_minutes', 32);
    }

    public function test_estimate_falls_back_cleanly_when_no_model_exists(): void
    {
        // No artifact on the faked disk → fallback to service.avg_service_minutes.
        [$user] = $this->accountingGeneralScenario(peopleAhead: 8, openWindows: 2);

        $this->withHeader('Authorization', "Bearer {$this->token($user)}")
            ->getJson('/api/queue/estimate')
            ->assertOk()
            ->assertJsonPath('data.basis', PredictionBasis::Fallback->value)
            ->assertJsonPath('data.estimated_minutes', 16) // (8 × 4) ÷ 2 from the avg
            ->assertJsonPath('data.model_version', null);
    }

    public function test_estimate_404_when_no_active_ticket(): void
    {
        $user = User::factory()->create();

        $this->withHeader('Authorization', "Bearer {$this->token($user)}")
            ->getJson('/api/queue/estimate')
            ->assertNotFound();
    }

    public function test_estimate_requires_authentication(): void
    {
        $this->getJson('/api/queue/estimate')->assertUnauthorized();
    }

    public function test_status_eta_is_populated_from_the_predictor(): void
    {
        [$user, , , $service] = $this->accountingGeneralScenario(peopleAhead: 8, openWindows: 2);
        $this->seedFallbackModel($service->id, 4.0);

        $this->withHeader('Authorization', "Bearer {$this->token($user)}")
            ->getJson('/api/queue/status')
            ->assertOk()
            ->assertJsonPath('data.eta.estimated_minutes', 16)
            ->assertJsonPath('data.eta.active_windows', 2)
            ->assertJsonPath('data.eta.basis', PredictionBasis::Model->value);
    }
}
