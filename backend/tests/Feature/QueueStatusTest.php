<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Queue;
use App\Models\QueueGroup;
use App\Models\QueueTicket;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueueStatusTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $user): string
    {
        return auth('api')->login($user);
    }

    public function test_status_returns_position_and_people_ahead(): void
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);
        $service = Service::factory()->forQueueGroup($group)->create();
        $queue = Queue::factory()->for($office)->create();

        $base = [
            'queue_id' => $queue->id,
            'queue_group_id' => $group->id,
            'service_id' => $service->id,
        ];

        // Two earlier waiters in the same group.
        QueueTicket::factory()->waiting()->create($base + [
            'user_id' => User::factory(),
            'joined_at' => now()->subMinutes(10),
        ]);
        QueueTicket::factory()->waiting()->create($base + [
            'user_id' => User::factory(),
            'joined_at' => now()->subMinutes(5),
        ]);

        $user = User::factory()->create();
        QueueTicket::factory()->waiting()->create($base + [
            'user_id' => $user->id,
            'joined_at' => now()->subMinute(),
            'ticket_number' => 'A-003',
        ]);

        $this->withHeader('Authorization', "Bearer {$this->token($user)}")
            ->getJson('/api/queue/status')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'ticket_number', 'status', 'position', 'people_ahead', 'current_number', 'eta',
                    'office', 'queue_group', 'service',
                ],
            ])
            ->assertJsonPath('data.ticket_number', 'A-003')
            ->assertJsonPath('data.people_ahead', 2)
            ->assertJsonPath('data.position', 3);
    }

    public function test_priority_ticket_is_ordered_ahead(): void
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);
        $service = Service::factory()->forQueueGroup($group)->create();
        $queue = Queue::factory()->for($office)->create();

        $base = [
            'queue_id' => $queue->id,
            'queue_group_id' => $group->id,
            'service_id' => $service->id,
        ];

        // A normal student who joined first.
        $earlyUser = User::factory()->create();
        QueueTicket::factory()->waiting()->create($base + [
            'user_id' => $earlyUser->id,
            'priority' => 0,
            'joined_at' => now()->subMinutes(10),
            'ticket_number' => 'A-001',
        ]);

        // A priority (e.g. PWD) student who joined later but jumps ahead.
        $priorityUser = User::factory()->create();
        QueueTicket::factory()->waiting()->priority(1)->create($base + [
            'user_id' => $priorityUser->id,
            'joined_at' => now()->subMinute(),
            'ticket_number' => 'A-002',
        ]);

        // Priority student is first: nobody ahead.
        $this->withHeader('Authorization', "Bearer {$this->token($priorityUser)}")
            ->getJson('/api/queue/status')
            ->assertOk()
            ->assertJsonPath('data.ticket_number', 'A-002')
            ->assertJsonPath('data.people_ahead', 0)
            ->assertJsonPath('data.position', 1);

        // The earlier normal student is now bumped to second.
        $this->withHeader('Authorization', "Bearer {$this->token($earlyUser)}")
            ->getJson('/api/queue/status')
            ->assertOk()
            ->assertJsonPath('data.ticket_number', 'A-001')
            ->assertJsonPath('data.people_ahead', 1)
            ->assertJsonPath('data.position', 2);
    }

    public function test_status_reports_current_serving_number(): void
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);
        $service = Service::factory()->forQueueGroup($group)->create();
        $queue = Queue::factory()->for($office)->create();

        $base = [
            'queue_id' => $queue->id,
            'queue_group_id' => $group->id,
            'service_id' => $service->id,
        ];

        QueueTicket::factory()->serving()->create($base + [
            'user_id' => User::factory(),
            'ticket_number' => 'A-005',
        ]);

        $user = User::factory()->create();
        QueueTicket::factory()->waiting()->create($base + [
            'user_id' => $user->id,
            'ticket_number' => 'A-006',
        ]);

        $this->withHeader('Authorization', "Bearer {$this->token($user)}")
            ->getJson('/api/queue/status')
            ->assertOk()
            ->assertJsonPath('data.current_number', 'A-005');
    }

    public function test_status_returns_null_when_no_active_ticket(): void
    {
        $user = User::factory()->create();

        $this->withHeader('Authorization', "Bearer {$this->token($user)}")
            ->getJson('/api/queue/status')
            ->assertOk()
            ->assertExactJson(['data' => null]);
    }

    public function test_status_requires_authentication(): void
    {
        $this->getJson('/api/queue/status')->assertUnauthorized();
    }
}
