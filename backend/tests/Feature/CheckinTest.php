<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\LocationLog;
use App\Models\Office;
use App\Models\Queue;
use App\Models\QueueGroup;
use App\Models\QueueTicket;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckinTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $user): string
    {
        return auth('api')->login($user);
    }

    /**
     * @return array{0: User, 1: QueueTicket}
     */
    private function userWithTicket(string $number = 'A-001'): array
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
            'ticket_number' => $number,
        ]);

        return [$user, $ticket];
    }

    public function test_in_range_scan_marks_ticket_ready(): void
    {
        [$user, $ticket] = $this->userWithTicket();

        $this->withHeader('Authorization', "Bearer {$this->token($user)}")
            ->postJson('/api/checkin', [
                'ticket_number' => 'A-001',
                'latitude' => 14.600120,
                'longitude' => 121.050130,
            ])
            ->assertOk()
            ->assertJsonPath('data.ticket_number', 'A-001')
            ->assertJsonPath('data.status', TicketStatus::Ready->value);

        $this->assertSame(TicketStatus::Ready, $ticket->refresh()->status);
        $this->assertSame(1, LocationLog::query()->where('ticket_id', $ticket->id)->count());
    }

    public function test_out_of_range_scan_is_rejected_and_keeps_ticket_waiting(): void
    {
        [$user, $ticket] = $this->userWithTicket();

        $this->withHeader('Authorization', "Bearer {$this->token($user)}")
            ->postJson('/api/checkin', [
                'ticket_number' => 'A-001',
                'latitude' => 14.605000,
                'longitude' => 121.055000,
            ])
            ->assertStatus(409)
            ->assertJsonStructure(['message', 'distance_m', 'radius_m']);

        // Ticket unchanged, but the out-of-range attempt is still logged.
        $this->assertSame(TicketStatus::Waiting, $ticket->refresh()->status);
        $this->assertSame(1, LocationLog::query()->where('ticket_id', $ticket->id)->count());
    }

    public function test_scanning_a_ticket_not_owned_by_the_user_returns_not_found(): void
    {
        [, $ticket] = $this->userWithTicket('A-007');

        // A different authenticated student scans someone else's number.
        $stranger = User::factory()->create();

        $this->withHeader('Authorization', "Bearer {$this->token($stranger)}")
            ->postJson('/api/checkin', [
                'ticket_number' => 'A-007',
                'latitude' => 14.600120,
                'longitude' => 121.050130,
            ])
            ->assertNotFound();

        $this->assertSame(TicketStatus::Waiting, $ticket->refresh()->status);
    }

    public function test_unknown_ticket_number_returns_not_found(): void
    {
        [$user] = $this->userWithTicket();

        $this->withHeader('Authorization', "Bearer {$this->token($user)}")
            ->postJson('/api/checkin', [
                'ticket_number' => 'Z-999',
                'latitude' => 14.600120,
                'longitude' => 121.050130,
            ])
            ->assertNotFound();
    }

    public function test_invalid_payload_is_rejected(): void
    {
        [$user] = $this->userWithTicket();

        $this->withHeader('Authorization', "Bearer {$this->token($user)}")
            ->postJson('/api/checkin', [
                'latitude' => 14.600120,
                'longitude' => 121.050130,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ticket_number']);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/checkin', [
            'ticket_number' => 'A-001',
            'latitude' => 14.6001,
            'longitude' => 121.0501,
        ])->assertUnauthorized();
    }
}
