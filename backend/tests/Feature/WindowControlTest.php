<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Office;
use App\Models\QueueGroup;
use App\Models\QueueTicket;
use App\Models\Service;
use App\Models\User;
use App\Models\Window;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WindowControlTest extends TestCase
{
    use RefreshDatabase;

    private function staffToken(): string
    {
        return auth('api')->login(User::factory()->staff()->create());
    }

    private function ticket(QueueGroup $group, string $number, string $joinedAt, int $priority = 0): QueueTicket
    {
        $service = Service::factory()->forQueueGroup($group)->create();

        return QueueTicket::factory()
            ->for($group)
            ->for($service)
            ->create([
                'ticket_number' => $number,
                'status' => TicketStatus::Waiting,
                'priority' => $priority,
                'joined_at' => $joinedAt,
            ]);
    }

    /**
     * @return array{0: Window, 1: QueueGroup}
     */
    private function windowWithGroup(): array
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);
        $window = Window::factory()->for($office)->create();
        $window->queueGroups()->attach($group->id);

        return [$window, $group];
    }

    public function test_available_assigns_oldest_eligible_ticket(): void
    {
        [$window, $group] = $this->windowWithGroup();
        $this->ticket($group, 'A-001', '2026-06-18 09:00:00');
        $this->ticket($group, 'A-002', '2026-06-18 09:05:00');

        $this->withHeader('Authorization', "Bearer {$this->staffToken()}")
            ->postJson("/api/windows/{$window->id}/available")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'ticket_number', 'status', 'status_label', 'priority',
                    'joined_at', 'called_at', 'served_at',
                    'student' => ['id', 'name', 'student_no'],
                    'office' => ['id', 'name'],
                    'queue_group' => ['id', 'name', 'prefix'],
                    'service' => ['id', 'name'],
                ],
            ])
            ->assertJsonPath('data.ticket_number', 'A-001')
            ->assertJsonPath('data.status', TicketStatus::Serving->value);

        $this->assertDatabaseHas('window_assignments', [
            'window_id' => $window->id,
            'served_at' => null,
        ]);
    }

    public function test_available_returns_null_when_queue_empty(): void
    {
        [$window] = $this->windowWithGroup();

        $this->withHeader('Authorization', "Bearer {$this->staffToken()}")
            ->postJson("/api/windows/{$window->id}/available")
            ->assertOk()
            ->assertExactJson(['data' => null]);
    }

    public function test_serve_completes_current_ticket(): void
    {
        [$window, $group] = $this->windowWithGroup();
        $this->ticket($group, 'A-001', '2026-06-18 09:00:00');
        $token = $this->staffToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/windows/{$window->id}/available")->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/windows/{$window->id}/serve")
            ->assertOk()
            ->assertJsonPath('data.ticket_number', 'A-001')
            ->assertJsonPath('data.status', TicketStatus::Served->value);

        $this->assertDatabaseMissing('window_assignments', [
            'window_id' => $window->id,
            'served_at' => null,
        ]);
    }

    public function test_serve_without_current_assignment_is_404(): void
    {
        [$window] = $this->windowWithGroup();

        $this->withHeader('Authorization', "Bearer {$this->staffToken()}")
            ->postJson("/api/windows/{$window->id}/serve")
            ->assertNotFound();
    }

    public function test_skip_marks_skipped_and_auto_assigns_next(): void
    {
        [$window, $group] = $this->windowWithGroup();
        $first = $this->ticket($group, 'A-001', '2026-06-18 09:00:00');
        $this->ticket($group, 'A-002', '2026-06-18 09:05:00');
        $token = $this->staffToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/windows/{$window->id}/available")->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/windows/{$window->id}/skip")
            ->assertOk()
            ->assertJsonPath('data.ticket_number', 'A-002')
            ->assertJsonPath('data.status', TicketStatus::Serving->value);

        $this->assertSame(TicketStatus::Skipped, $first->refresh()->status);
    }

    public function test_skip_returns_null_when_no_more_tickets(): void
    {
        [$window, $group] = $this->windowWithGroup();
        $this->ticket($group, 'A-001', '2026-06-18 09:00:00');
        $token = $this->staffToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/windows/{$window->id}/available")->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/windows/{$window->id}/skip")
            ->assertOk()
            ->assertExactJson(['data' => null]);
    }

    public function test_skip_without_current_assignment_is_404(): void
    {
        [$window] = $this->windowWithGroup();

        $this->withHeader('Authorization', "Bearer {$this->staffToken()}")
            ->postJson("/api/windows/{$window->id}/skip")
            ->assertNotFound();
    }

    public function test_recall_returns_current_ticket_unchanged(): void
    {
        [$window, $group] = $this->windowWithGroup();
        $this->ticket($group, 'A-001', '2026-06-18 09:00:00');
        $token = $this->staffToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/windows/{$window->id}/available")->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/windows/{$window->id}/recall")
            ->assertOk()
            ->assertJsonPath('data.ticket_number', 'A-001')
            ->assertJsonPath('data.status', TicketStatus::Serving->value);
    }

    public function test_recall_without_current_assignment_is_404(): void
    {
        [$window] = $this->windowWithGroup();

        $this->withHeader('Authorization', "Bearer {$this->staffToken()}")
            ->postJson("/api/windows/{$window->id}/recall")
            ->assertNotFound();
    }

    public function test_admin_may_operate_windows(): void
    {
        [$window, $group] = $this->windowWithGroup();
        $this->ticket($group, 'A-001', '2026-06-18 09:00:00');
        $adminToken = auth('api')->login(User::factory()->admin()->create());

        $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->postJson("/api/windows/{$window->id}/available")
            ->assertOk()
            ->assertJsonPath('data.ticket_number', 'A-001');
    }

    public function test_student_is_forbidden_from_window_controls(): void
    {
        [$window, $group] = $this->windowWithGroup();
        $this->ticket($group, 'A-001', '2026-06-18 09:00:00');
        $studentToken = auth('api')->login(User::factory()->student()->create());

        $this->withHeader('Authorization', "Bearer {$studentToken}")
            ->postJson("/api/windows/{$window->id}/available")
            ->assertForbidden();
    }

    public function test_window_controls_require_authentication(): void
    {
        [$window] = $this->windowWithGroup();

        $this->postJson("/api/windows/{$window->id}/available")
            ->assertUnauthorized();
    }
}
