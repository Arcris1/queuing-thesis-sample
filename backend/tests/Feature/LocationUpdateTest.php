<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LocationLog;
use App\Models\Office;
use App\Models\Queue;
use App\Models\QueueGroup;
use App\Models\QueueTicket;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $user): string
    {
        return auth('api')->login($user);
    }

    /**
     * Seed an office at the plan §8 coordinates with an active waiting ticket.
     *
     * @return array{0: User, 1: QueueTicket}
     */
    private function userWithTicket(): array
    {
        $office = Office::factory()->create([
            'latitude' => 14.600100,
            'longitude' => 121.050100,
            'geofence_radius_m' => 15,
        ]);
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);
        $service = Service::factory()->forQueueGroup($group)->create();
        $queue = Queue::factory()->for($office)->create();

        $user = User::factory()->create();
        $ticket = QueueTicket::factory()->waiting()->create([
            'queue_id' => $queue->id,
            'queue_group_id' => $group->id,
            'service_id' => $service->id,
            'user_id' => $user->id,
            'ticket_number' => 'A-001',
        ]);

        return [$user, $ticket];
    }

    public function test_within_radius_reports_eligible_and_logs_the_sample(): void
    {
        [$user, $ticket] = $this->userWithTicket();

        // ~8.4 m from the office.
        $this->withHeader('Authorization', "Bearer {$this->token($user)}")
            ->postJson('/api/location/update', [
                'latitude' => 14.600120,
                'longitude' => 121.050130,
            ])
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['distance_m', 'within_range', 'radius_m', 'recorded_at', 'office' => ['id', 'name']],
            ])
            ->assertJsonPath('data.within_range', true)
            ->assertJsonPath('data.radius_m', 15);

        $this->assertSame(1, LocationLog::query()->where('ticket_id', $ticket->id)->count());

        $log = LocationLog::query()->where('ticket_id', $ticket->id)->first();
        $this->assertNotNull($log->distance_m);
        $this->assertLessThan(15, (float) $log->distance_m);
    }

    public function test_outside_radius_reports_not_eligible(): void
    {
        [$user] = $this->userWithTicket();

        // Far from the office (~hundreds of meters).
        $this->withHeader('Authorization', "Bearer {$this->token($user)}")
            ->postJson('/api/location/update', [
                'latitude' => 14.605000,
                'longitude' => 121.055000,
            ])
            ->assertOk()
            ->assertJsonPath('data.within_range', false);
    }

    public function test_invalid_coordinates_are_rejected(): void
    {
        [$user] = $this->userWithTicket();

        $this->withHeader('Authorization', "Bearer {$this->token($user)}")
            ->postJson('/api/location/update', [
                'latitude' => 200,
                'longitude' => 999,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_no_active_ticket_returns_not_found(): void
    {
        $user = User::factory()->create();

        $this->withHeader('Authorization', "Bearer {$this->token($user)}")
            ->postJson('/api/location/update', [
                'latitude' => 14.600120,
                'longitude' => 121.050130,
            ])
            ->assertNotFound();
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/location/update', [
            'latitude' => 14.6001,
            'longitude' => 121.0501,
        ])->assertUnauthorized();
    }
}
