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

class WindowQueueGroupAdminTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        return auth('api')->login(User::factory()->admin()->create());
    }

    private function ticket(QueueGroup $group, string $number): QueueTicket
    {
        $service = Service::factory()->forQueueGroup($group)->create();

        return QueueTicket::factory()
            ->for($group)
            ->for($service)
            ->create([
                'ticket_number' => $number,
                'status' => TicketStatus::Waiting,
                'priority' => 0,
                'joined_at' => '2026-06-18 09:00:00',
            ]);
    }

    public function test_attach_adds_queue_group_to_window(): void
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A', 'name' => 'Accounting General']);
        $window = Window::factory()->for($office)->create();

        $this->withHeader('Authorization', "Bearer {$this->adminToken()}")
            ->postJson("/api/admin/windows/{$window->id}/queue-groups", ['queue_group_id' => $group->id])
            ->assertOk()
            ->assertJsonPath('data.id', $window->id)
            ->assertJsonPath('data.queue_groups.0.id', $group->id);

        $this->assertDatabaseHas('window_queue_groups', [
            'window_id' => $window->id,
            'queue_group_id' => $group->id,
        ]);
    }

    public function test_attach_is_idempotent(): void
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);
        $window = Window::factory()->for($office)->create();
        $window->queueGroups()->attach($group->id);
        $token = $this->adminToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/admin/windows/{$window->id}/queue-groups", ['queue_group_id' => $group->id])
            ->assertOk();

        $this->assertSame(1, $window->queueGroups()->count());
    }

    public function test_cross_office_attach_is_rejected_422(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $window = Window::factory()->for($officeA)->create();
        $foreignGroup = QueueGroup::factory()->for($officeB)->create();

        $this->withHeader('Authorization', "Bearer {$this->adminToken()}")
            ->postJson("/api/admin/windows/{$window->id}/queue-groups", ['queue_group_id' => $foreignGroup->id])
            ->assertStatus(422);

        $this->assertDatabaseMissing('window_queue_groups', [
            'window_id' => $window->id,
            'queue_group_id' => $foreignGroup->id,
        ]);
    }

    public function test_detach_removes_queue_group(): void
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create();
        $window = Window::factory()->for($office)->create();
        $window->queueGroups()->attach($group->id);

        $this->withHeader('Authorization', "Bearer {$this->adminToken()}")
            ->deleteJson("/api/admin/windows/{$window->id}/queue-groups/{$group->id}")
            ->assertOk()
            ->assertJsonPath('data.queue_groups', []);

        $this->assertDatabaseMissing('window_queue_groups', [
            'window_id' => $window->id,
            'queue_group_id' => $group->id,
        ]);
    }

    public function test_detach_unattached_group_is_404(): void
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create();
        $window = Window::factory()->for($office)->create();

        $this->withHeader('Authorization', "Bearer {$this->adminToken()}")
            ->deleteJson("/api/admin/windows/{$window->id}/queue-groups/{$group->id}")
            ->assertNotFound();
    }

    /**
     * §5.4 proof: attaching a queue group immediately widens the routing engine's
     * candidate set. A window with no groups assigns nothing; after attaching a
     * group with a waiting ticket, the very next /available call assigns it — no
     * code change, purely the live pivot.
     */
    public function test_attach_widens_routing_immediately(): void
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);
        $window = Window::factory()->for($office)->create();
        $this->ticket($group, 'A-001');
        $token = $this->adminToken();

        // Before attaching: window serves no groups → nothing eligible.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/windows/{$window->id}/available")
            ->assertOk()
            ->assertExactJson(['data' => null]);

        // Attach the group.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/admin/windows/{$window->id}/queue-groups", ['queue_group_id' => $group->id])
            ->assertOk();

        // After attaching: the same window now reaches the group's ticket.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/windows/{$window->id}/available")
            ->assertOk()
            ->assertJsonPath('data.ticket_number', 'A-001')
            ->assertJsonPath('data.status', TicketStatus::Serving->value);
    }

    /**
     * Detaching narrows the candidate set: a window serving a group stops being
     * able to assign that group's tickets once detached.
     */
    public function test_detach_narrows_routing(): void
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);
        $window = Window::factory()->for($office)->create();
        $window->queueGroups()->attach($group->id);
        $this->ticket($group, 'A-001');
        $token = $this->adminToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/admin/windows/{$window->id}/queue-groups/{$group->id}")
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/windows/{$window->id}/available")
            ->assertOk()
            ->assertExactJson(['data' => null]);
    }

    public function test_staff_is_forbidden_from_attach(): void
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create();
        $window = Window::factory()->for($office)->create();
        $staffToken = auth('api')->login(User::factory()->staff()->create());

        $this->withHeader('Authorization', "Bearer {$staffToken}")
            ->postJson("/api/admin/windows/{$window->id}/queue-groups", ['queue_group_id' => $group->id])
            ->assertForbidden();
    }

    public function test_student_is_forbidden_from_attach(): void
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create();
        $window = Window::factory()->for($office)->create();
        $studentToken = auth('api')->login(User::factory()->student()->create());

        $this->withHeader('Authorization', "Bearer {$studentToken}")
            ->postJson("/api/admin/windows/{$window->id}/queue-groups", ['queue_group_id' => $group->id])
            ->assertForbidden();
    }

    public function test_attach_requires_authentication(): void
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create();
        $window = Window::factory()->for($office)->create();

        $this->postJson("/api/admin/windows/{$window->id}/queue-groups", ['queue_group_id' => $group->id])
            ->assertUnauthorized();
    }
}
