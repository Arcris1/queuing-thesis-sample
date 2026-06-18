<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PresenceStatus;
use App\Enums\TicketStatus;
use App\Models\Heartbeat;
use App\Models\LocationLog;
use App\Models\Office;
use App\Models\Queue;
use App\Models\QueueGroup;
use App\Models\QueueTicket;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeartbeatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('queue_system.presence.away_after_seconds', 120);
        config()->set('queue_system.presence.offline_after_seconds', 300);
        config()->set('queue_system.presence.removed_after_seconds', 600);
    }

    private function token(User $user): string
    {
        return auth('api')->login($user);
    }

    /**
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

    public function test_heartbeat_records_last_seen_and_returns_active_presence(): void
    {
        [$user, $ticket] = $this->userWithTicket();

        $this->withHeader('Authorization', "Bearer {$this->token($user)}")
            ->postJson('/api/heartbeat', [
                'battery_level' => 80,
                'network_status' => 'wifi',
            ])
            ->assertOk()
            ->assertJsonPath('data.presence_status', PresenceStatus::Active->value)
            ->assertJsonPath('data.ticket.id', $ticket->id)
            ->assertJsonPath('data.ticket.ticket_number', 'A-001')
            ->assertJsonStructure(['data' => ['presence_status', 'last_seen', 'ticket' => ['id', 'status', 'office', 'queue_group', 'service']]]);

        $this->assertSame(1, Heartbeat::query()->where('ticket_id', $ticket->id)->count());

        /** @var Heartbeat $heartbeat */
        $heartbeat = Heartbeat::query()->where('ticket_id', $ticket->id)->first();
        $this->assertSame(80, $heartbeat->battery_level);
        $this->assertSame('wifi', $heartbeat->network_status);
        $this->assertTrue($heartbeat->last_seen->isToday());
    }

    public function test_heartbeat_with_coordinates_also_records_a_location_log(): void
    {
        [$user, $ticket] = $this->userWithTicket();

        $this->withHeader('Authorization', "Bearer {$this->token($user)}")
            ->postJson('/api/heartbeat', [
                'latitude' => 14.600120,
                'longitude' => 121.050130,
            ])
            ->assertOk();

        // The heartbeat doubled as a location ping (reusing the geofence pipeline).
        $this->assertSame(1, LocationLog::query()->where('ticket_id', $ticket->id)->count());

        /** @var LocationLog $log */
        $log = LocationLog::query()->where('ticket_id', $ticket->id)->first();
        // Distance is computed server-side (~8.4 m), never client-supplied.
        $this->assertLessThan(15, (float) $log->distance_m);
    }

    public function test_heartbeat_without_coordinates_records_no_location_log(): void
    {
        [$user, $ticket] = $this->userWithTicket();

        $this->withHeader('Authorization', "Bearer {$this->token($user)}")
            ->postJson('/api/heartbeat', ['battery_level' => 50])
            ->assertOk();

        $this->assertSame(0, LocationLog::query()->where('ticket_id', $ticket->id)->count());
    }

    public function test_heartbeat_without_active_ticket_returns_not_found(): void
    {
        $user = User::factory()->create();

        $this->withHeader('Authorization', "Bearer {$this->token($user)}")
            ->postJson('/api/heartbeat', ['battery_level' => 90])
            ->assertNotFound();
    }

    public function test_invalid_battery_level_is_rejected(): void
    {
        [$user] = $this->userWithTicket();

        $this->withHeader('Authorization', "Bearer {$this->token($user)}")
            ->postJson('/api/heartbeat', ['battery_level' => 150])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['battery_level']);
    }

    public function test_latitude_without_longitude_is_rejected(): void
    {
        [$user] = $this->userWithTicket();

        $this->withHeader('Authorization', "Bearer {$this->token($user)}")
            ->postJson('/api/heartbeat', ['latitude' => 14.6001])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['longitude']);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/heartbeat', ['battery_level' => 80])
            ->assertUnauthorized();
    }

    public function test_heartbeat_reinstates_a_standby_ticket(): void
    {
        [$user, $ticket] = $this->userWithTicket();
        $ticket->update([
            'status' => TicketStatus::Standby,
            'grace_until' => now()->subMinute(),
            'grace_offered_at' => now()->subMinutes(3),
        ]);

        $this->withHeader('Authorization', "Bearer {$this->token($user)}")
            ->postJson('/api/heartbeat', ['network_status' => 'wifi'])
            ->assertOk()
            ->assertJsonPath('data.ticket.status', TicketStatus::Waiting->value);

        $ticket->refresh();
        $this->assertSame(TicketStatus::Waiting, $ticket->status);
        $this->assertNull($ticket->grace_until);
        $this->assertNull($ticket->grace_offered_at);
    }
}
