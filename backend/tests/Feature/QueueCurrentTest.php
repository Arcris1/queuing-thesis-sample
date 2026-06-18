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

class QueueCurrentTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_shows_now_serving_and_waiting_per_group(): void
    {
        $office = Office::factory()->create(['name' => 'Registrar']);
        $groupA = QueueGroup::factory()->for($office)->create(['name' => 'General', 'prefix' => 'A']);
        $groupB = QueueGroup::factory()->for($office)->create(['name' => 'Transcript', 'prefix' => 'T']);
        $serviceA = Service::factory()->forQueueGroup($groupA)->create();
        $serviceB = Service::factory()->forQueueGroup($groupB)->create();
        $queue = Queue::factory()->for($office)->create();

        // Group A: one serving (A-014) + two waiting.
        QueueTicket::factory()->serving()->create([
            'queue_id' => $queue->id, 'queue_group_id' => $groupA->id, 'service_id' => $serviceA->id,
            'user_id' => User::factory(), 'ticket_number' => 'A-014',
        ]);
        QueueTicket::factory()->count(2)->waiting()->create([
            'queue_id' => $queue->id, 'queue_group_id' => $groupA->id, 'service_id' => $serviceA->id,
            'user_id' => User::factory(),
        ]);

        // Group B: one waiting, none serving.
        QueueTicket::factory()->waiting()->create([
            'queue_id' => $queue->id, 'queue_group_id' => $groupB->id, 'service_id' => $serviceB->id,
            'user_id' => User::factory(),
        ]);

        $response = $this->getJson("/api/queue/current?office_id={$office->id}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    [
                        'office' => ['id', 'name'],
                        'queue_groups' => [['id', 'name', 'prefix', 'current_number', 'waiting_count']],
                    ],
                ],
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(2, 'data.0.queue_groups');

        $response->assertJsonPath('data.0.queue_groups.0.name', 'General')
            ->assertJsonPath('data.0.queue_groups.0.current_number', 'A-014')
            ->assertJsonPath('data.0.queue_groups.0.waiting_count', 2)
            ->assertJsonPath('data.0.queue_groups.1.name', 'Transcript')
            ->assertJsonPath('data.0.queue_groups.1.current_number', null)
            ->assertJsonPath('data.0.queue_groups.1.waiting_count', 1);
    }

    public function test_current_returns_all_offices_when_no_filter(): void
    {
        Office::factory()->count(3)->create();

        $this->getJson('/api/queue/current')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_current_is_public(): void
    {
        Office::factory()->create();

        $this->getJson('/api/queue/current')->assertOk();
    }
}
