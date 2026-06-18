<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Office;
use App\Models\Queue;
use App\Models\QueueGroup;
use App\Models\QueueTicket;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueueLeaveTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $user): string
    {
        return auth('api')->login($user);
    }

    public function test_leave_marks_active_ticket_skipped(): void
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create();
        $service = Service::factory()->forQueueGroup($group)->create();
        $queue = Queue::factory()->for($office)->create();
        $user = User::factory()->create();

        $ticket = QueueTicket::factory()->waiting()->create([
            'queue_id' => $queue->id,
            'queue_group_id' => $group->id,
            'service_id' => $service->id,
            'user_id' => $user->id,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->token($user)}")
            ->postJson('/api/queue/leave')
            ->assertOk()
            ->assertJsonPath('data.id', $ticket->id)
            ->assertJsonPath('data.status', TicketStatus::Skipped->value);

        $ticket->refresh();
        $this->assertSame(TicketStatus::Skipped, $ticket->status);
        // Tickets are never hard-deleted; called/served timestamps untouched.
        $this->assertNull($ticket->called_at);
        $this->assertNull($ticket->served_at);
        $this->assertDatabaseHas('queue_tickets', ['id' => $ticket->id]);
    }

    public function test_leave_without_active_ticket_returns_404(): void
    {
        $user = User::factory()->create();

        $this->withHeader('Authorization', "Bearer {$this->token($user)}")
            ->postJson('/api/queue/leave')
            ->assertNotFound();
    }

    public function test_leave_requires_authentication(): void
    {
        $this->postJson('/api/queue/leave')->assertUnauthorized();
    }
}
