<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Office;
use App\Models\QueueGroup;
use App\Models\QueueTicket;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueueJoinTest extends TestCase
{
    use RefreshDatabase;

    private function actingToken(User $user): string
    {
        return auth('api')->login($user);
    }

    public function test_join_creates_waiting_ticket_with_group_prefixed_number(): void
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);
        $service = Service::factory()->forQueueGroup($group)->create(['name' => 'Assessment']);
        $user = User::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->actingToken($user)}")
            ->postJson('/api/queue/join', ['service_id' => $service->id]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id', 'ticket_number', 'status', 'priority', 'position', 'people_ahead',
                    'current_number', 'eta', 'joined_at',
                    'office' => ['id', 'name'],
                    'queue_group' => ['id', 'name', 'prefix'],
                    'service' => ['id', 'name'],
                ],
            ])
            ->assertJsonPath('data.ticket_number', 'A-001')
            ->assertJsonPath('data.status', TicketStatus::Waiting->value)
            ->assertJsonPath('data.position', 1)
            ->assertJsonPath('data.people_ahead', 0)
            ->assertJsonPath('data.queue_group.prefix', 'A')
            ->assertJsonPath('data.service.name', 'Assessment')
            ->assertJsonPath('data.office.id', $office->id);

        $this->assertDatabaseHas('queue_tickets', [
            'user_id' => $user->id,
            'queue_group_id' => $group->id,
            'service_id' => $service->id,
            'ticket_number' => 'A-001',
            'status' => TicketStatus::Waiting->value,
            'priority' => 0,
        ]);
    }

    public function test_join_numbers_are_sequential_within_a_group(): void
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'RG']);
        $service = Service::factory()->forQueueGroup($group)->create();

        $numbers = [];
        foreach (range(1, 3) as $i) {
            $user = User::factory()->create();
            $numbers[] = $this->withHeader('Authorization', "Bearer {$this->actingToken($user)}")
                ->postJson('/api/queue/join', ['service_id' => $service->id])
                ->json('data.ticket_number');
        }

        $this->assertSame(['RG-001', 'RG-002', 'RG-003'], $numbers);
    }

    public function test_numbering_is_independent_per_queue_group(): void
    {
        $office = Office::factory()->create();
        $groupA = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);
        $groupB = QueueGroup::factory()->for($office)->create(['prefix' => 'C']);
        $serviceA = Service::factory()->forQueueGroup($groupA)->create();
        $serviceB = Service::factory()->forQueueGroup($groupB)->create();

        $userA1 = User::factory()->create();
        $userB1 = User::factory()->create();
        $userA2 = User::factory()->create();

        $a1 = $this->withHeader('Authorization', "Bearer {$this->actingToken($userA1)}")
            ->postJson('/api/queue/join', ['service_id' => $serviceA->id])->json('data.ticket_number');
        $b1 = $this->withHeader('Authorization', "Bearer {$this->actingToken($userB1)}")
            ->postJson('/api/queue/join', ['service_id' => $serviceB->id])->json('data.ticket_number');
        $a2 = $this->withHeader('Authorization', "Bearer {$this->actingToken($userA2)}")
            ->postJson('/api/queue/join', ['service_id' => $serviceA->id])->json('data.ticket_number');

        $this->assertSame('A-001', $a1);
        $this->assertSame('C-001', $b1);
        $this->assertSame('A-002', $a2);
    }

    public function test_duplicate_active_ticket_in_same_group_is_rejected(): void
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);
        $service = Service::factory()->forQueueGroup($group)->create();
        $user = User::factory()->create();
        $token = $this->actingToken($user);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/queue/join', ['service_id' => $service->id])
            ->assertCreated();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/queue/join', ['service_id' => $service->id])
            ->assertStatus(409);

        $this->assertSame(1, QueueTicket::query()->where('user_id', $user->id)->count());
    }

    public function test_join_resolves_office_and_group_from_service_not_client(): void
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'T']);
        $service = Service::factory()->forQueueGroup($group)->create();
        $user = User::factory()->create();

        // Client sends a spurious office_id — it must be ignored.
        $this->withHeader('Authorization', "Bearer {$this->actingToken($user)}")
            ->postJson('/api/queue/join', ['service_id' => $service->id, 'office_id' => 999999])
            ->assertCreated()
            ->assertJsonPath('data.office.id', $office->id)
            ->assertJsonPath('data.queue_group.id', $group->id);
    }

    public function test_join_requires_authentication(): void
    {
        $service = Service::factory()->create();

        $this->postJson('/api/queue/join', ['service_id' => $service->id])
            ->assertUnauthorized();
    }

    public function test_join_rejects_unknown_service(): void
    {
        $user = User::factory()->create();

        $this->withHeader('Authorization', "Bearer {$this->actingToken($user)}")
            ->postJson('/api/queue/join', ['service_id' => 999999])
            ->assertStatus(422)
            ->assertJsonValidationErrors('service_id');
    }
}
