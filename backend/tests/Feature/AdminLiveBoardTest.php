<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PresenceStatus;
use App\Enums\TicketStatus;
use App\Enums\WindowStatus;
use App\Models\Heartbeat;
use App\Models\Office;
use App\Models\QueueGroup;
use App\Models\QueueTicket;
use App\Models\Service;
use App\Models\User;
use App\Models\Window;
use App\Models\WindowAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLiveBoardTest extends TestCase
{
    use RefreshDatabase;

    private function staffToken(): string
    {
        return auth('api')->login(User::factory()->staff()->create());
    }

    private function waitingTicket(QueueGroup $group, Service $service, string $number, string $joinedAt): QueueTicket
    {
        return QueueTicket::factory()->for($group)->for($service)->create([
            'ticket_number' => $number,
            'status' => TicketStatus::Waiting,
            'priority' => 0,
            'joined_at' => $joinedAt,
        ]);
    }

    public function test_live_board_shows_now_serving_waiting_and_window_assignment(): void
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A', 'name' => 'Accounting']);
        $service = Service::factory()->forQueueGroup($group)->create();
        $window = Window::factory()->for($office)->open()->create(['name' => 'Window 1']);
        $window->queueGroups()->attach($group->id);

        // Two waiting tickets (line) ordered by joined_at.
        $this->waitingTicket($group, $service, 'A-002', '2026-06-18 09:00:00');
        $this->waitingTicket($group, $service, 'A-003', '2026-06-18 09:05:00');

        // A serving ticket → now_serving + a window assignment.
        $student = User::factory()->student()->create(['name' => 'Jane Doe']);
        $serving = QueueTicket::factory()->for($group)->for($service)->for($student)->create([
            'ticket_number' => 'A-001',
            'status' => TicketStatus::Serving,
            'joined_at' => '2026-06-18 08:55:00',
            'called_at' => '2026-06-18 09:10:00',
        ]);
        WindowAssignment::factory()->create([
            'window_id' => $window->id,
            'ticket_id' => $serving->id,
            'assigned_at' => '2026-06-18 09:10:00',
            'served_at' => null,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->staffToken()}")
            ->getJson("/api/admin/queue/{$office->id}/live")
            ->assertOk()
            ->assertJsonPath('data.office.id', $office->id);

        // Queue group line.
        $response
            ->assertJsonPath('data.queue_groups.0.id', $group->id)
            ->assertJsonPath('data.queue_groups.0.now_serving', 'A-001')
            ->assertJsonPath('data.queue_groups.0.waiting_count', 2)
            ->assertJsonPath('data.queue_groups.0.counts.waiting', 2);

        // Waiting tickets carry position, presence, and eta.
        $response
            ->assertJsonPath('data.queue_groups.0.tickets.0.ticket_number', 'A-002')
            ->assertJsonPath('data.queue_groups.0.tickets.0.position', 1)
            ->assertJsonPath('data.queue_groups.0.tickets.1.position', 2);

        $this->assertArrayHasKey('presence_status', $response->json('data.queue_groups.0.tickets.0'));
        $this->assertArrayHasKey('eta', $response->json('data.queue_groups.0.tickets.0'));

        // Window state + current assignment.
        $response
            ->assertJsonPath('data.windows.0.id', $window->id)
            ->assertJsonPath('data.windows.0.status', WindowStatus::Open->value)
            ->assertJsonPath('data.windows.0.queue_groups.0.id', $group->id)
            ->assertJsonPath('data.windows.0.current_assignment.ticket_number', 'A-001')
            ->assertJsonPath('data.windows.0.current_assignment.student.name', 'Jane Doe');
    }

    public function test_live_board_presence_reflects_latest_heartbeat(): void
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);
        $service = Service::factory()->forQueueGroup($group)->create();

        // Away student: last heartbeat older than the away threshold (2 min).
        $awayTicket = $this->waitingTicket($group, $service, 'A-001', '2026-06-18 09:00:00');
        Heartbeat::factory()->create([
            'ticket_id' => $awayTicket->id,
            'last_seen' => now()->subMinutes(3),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->staffToken()}")
            ->getJson("/api/admin/queue/{$office->id}/live")
            ->assertOk();

        $response->assertJsonPath('data.queue_groups.0.tickets.0.presence_status', PresenceStatus::Away->value);
        $this->assertSame(1, $response->json('data.queue_groups.0.counts.away'));
        $this->assertSame(0, $response->json('data.queue_groups.0.counts.active'));
    }

    public function test_idle_window_has_null_assignment(): void
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create();
        $window = Window::factory()->for($office)->create(['name' => 'Window 1']);
        $window->queueGroups()->attach($group->id);

        $this->withHeader('Authorization', "Bearer {$this->staffToken()}")
            ->getJson("/api/admin/queue/{$office->id}/live")
            ->assertOk()
            ->assertJsonPath('data.windows.0.current_assignment', null);
    }

    public function test_student_is_forbidden(): void
    {
        $office = Office::factory()->create();
        $studentToken = auth('api')->login(User::factory()->student()->create());

        $this->withHeader('Authorization', "Bearer {$studentToken}")
            ->getJson("/api/admin/queue/{$office->id}/live")
            ->assertForbidden();
    }

    public function test_requires_authentication(): void
    {
        $office = Office::factory()->create();

        $this->getJson("/api/admin/queue/{$office->id}/live")->assertUnauthorized();
    }
}
