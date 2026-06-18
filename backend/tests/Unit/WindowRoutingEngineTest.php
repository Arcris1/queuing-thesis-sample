<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\TicketStatus;
use App\Enums\WindowStatus;
use App\Models\Heartbeat;
use App\Models\Office;
use App\Models\QueueGroup;
use App\Models\QueueTicket;
use App\Models\Service;
use App\Models\Window;
use App\Models\WindowAssignment;
use App\Services\RoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WindowRoutingEngineTest extends TestCase
{
    use RefreshDatabase;

    private RoutingService $routing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->routing = app(RoutingService::class);
    }

    /**
     * Create a waiting ticket in a queue group with a controllable joined_at.
     */
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

    public function test_assigns_oldest_eligible_ticket_across_multiple_groups(): void
    {
        $office = Office::factory()->create();
        $general = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);
        $assessment = QueueGroup::factory()->for($office)->create(['prefix' => 'B']);

        // Oldest overall is in the second group.
        $this->ticket($general, 'A-001', '2026-06-18 09:05:00');
        $oldest = $this->ticket($assessment, 'B-001', '2026-06-18 09:00:00');

        $window = Window::factory()->for($office)->create();
        $window->queueGroups()->attach([$general->id, $assessment->id]);

        $assignment = $this->routing->assignNext($window);

        $this->assertNotNull($assignment);
        $this->assertSame($oldest->id, $assignment->ticket_id);
        $this->assertSame(TicketStatus::Serving, $assignment->ticket->status);
        $this->assertNotNull($assignment->ticket->called_at);
        $this->assertNull($assignment->served_at);
        $this->assertSame(WindowStatus::Open, $window->refresh()->status);
    }

    public function test_priority_ticket_served_before_older_normal_ticket(): void
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);

        // Normal ticket joined earlier, priority ticket joined later — priority wins.
        $this->ticket($group, 'A-001', '2026-06-18 09:00:00', priority: 0);
        $priority = $this->ticket($group, 'A-002', '2026-06-18 09:10:00', priority: 5);

        $window = Window::factory()->for($office)->create();
        $window->queueGroups()->attach($group->id);

        $assignment = $this->routing->assignNext($window);

        $this->assertSame($priority->id, $assignment?->ticket_id);
    }

    public function test_two_windows_do_not_double_assign_the_same_ticket(): void
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);

        $first = $this->ticket($group, 'A-001', '2026-06-18 09:00:00');
        $second = $this->ticket($group, 'A-002', '2026-06-18 09:01:00');

        $win1 = Window::factory()->for($office)->create();
        $win2 = Window::factory()->for($office)->create();
        $win1->queueGroups()->attach($group->id);
        $win2->queueGroups()->attach($group->id);

        $a1 = $this->routing->assignNext($win1);
        $a2 = $this->routing->assignNext($win2);

        $this->assertSame($first->id, $a1?->ticket_id);
        $this->assertSame($second->id, $a2?->ticket_id);
        $this->assertNotSame($a1?->ticket_id, $a2?->ticket_id);
    }

    public function test_returns_null_when_no_eligible_ticket(): void
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);
        $window = Window::factory()->for($office)->create();
        $window->queueGroups()->attach($group->id);

        $this->assertNull($this->routing->assignNext($window));
    }

    public function test_returns_null_when_window_has_no_queue_groups(): void
    {
        $office = Office::factory()->create();
        $window = Window::factory()->for($office)->create();

        $this->assertNull($this->routing->assignNext($window));
    }

    public function test_enforces_one_open_assignment_per_window(): void
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);

        $first = $this->ticket($group, 'A-001', '2026-06-18 09:00:00');
        $this->ticket($group, 'A-002', '2026-06-18 09:01:00');

        $window = Window::factory()->for($office)->create();
        $window->queueGroups()->attach($group->id);

        $a1 = $this->routing->assignNext($window);
        $a2 = $this->routing->assignNext($window);

        // Second call returns the same open assignment, does not grab A-002.
        $this->assertSame($a1?->id, $a2?->id);
        $this->assertSame($first->id, $a2?->ticket_id);
        $this->assertSame(1, WindowAssignment::query()->where('window_id', $window->id)->whereNull('served_at')->count());
    }

    public function test_dynamic_enable_widens_candidate_set_with_no_code_change(): void
    {
        $office = Office::factory()->create();
        $served = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);
        $other = QueueGroup::factory()->for($office)->create(['prefix' => 'B']);

        // Only the group NOT yet attached has a waiting ticket.
        $ticket = $this->ticket($other, 'B-001', '2026-06-18 09:00:00');

        $window = Window::factory()->for($office)->create();
        $window->queueGroups()->attach($served->id);

        // Idle window cannot reach B-001 yet.
        $this->assertNull($this->routing->assignNext($window));

        // Attach the extra group — a one-row pivot change (§5.4) — and re-run.
        $window->queueGroups()->attach($other->id);

        $assignment = $this->routing->assignNext($window);
        $this->assertSame($ticket->id, $assignment?->ticket_id);
    }

    public function test_skips_ineligible_ticket_with_away_presence(): void
    {
        config()->set('queue_system.presence.away_after_seconds', 120);
        config()->set('queue_system.presence.offline_after_seconds', 300);
        config()->set('queue_system.presence.removed_after_seconds', 600);

        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);

        $away = $this->ticket($group, 'A-001', '2026-06-18 09:00:00');
        $active = $this->ticket($group, 'A-002', '2026-06-18 09:01:00');

        // Oldest ticket is Away (last heartbeat well past the away threshold).
        Heartbeat::factory()->create([
            'user_id' => $away->user_id,
            'ticket_id' => $away->id,
            'last_seen' => now()->subMinutes(4),
        ]);
        // Second ticket is Active (fresh heartbeat).
        Heartbeat::factory()->create([
            'user_id' => $active->user_id,
            'ticket_id' => $active->id,
            'last_seen' => now(),
        ]);

        $window = Window::factory()->for($office)->create();
        $window->queueGroups()->attach($group->id);

        $assignment = $this->routing->assignNext($window);

        // Away ticket is skipped; the active one behind it is assigned.
        $this->assertSame($active->id, $assignment?->ticket_id);
        $this->assertSame(TicketStatus::Waiting, $away->refresh()->status);
    }

    public function test_serve_closes_assignment_and_marks_ticket_served(): void
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);
        $this->ticket($group, 'A-001', '2026-06-18 09:00:00');

        $window = Window::factory()->for($office)->create();
        $window->queueGroups()->attach($group->id);

        $assignment = $this->routing->assignNext($window);
        $served = $this->routing->serve($window);

        $this->assertSame($assignment?->ticket_id, $served?->id);
        $this->assertSame(TicketStatus::Served, $served?->status);
        $this->assertNotNull($served?->served_at);
        $this->assertNotNull($assignment?->refresh()->served_at);
        $this->assertSame(WindowStatus::Idle, $window->refresh()->status);
        $this->assertFalse($window->currentAssignment()->exists());
    }

    public function test_skip_marks_ticket_skipped_and_auto_assigns_next(): void
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);

        $first = $this->ticket($group, 'A-001', '2026-06-18 09:00:00');
        $second = $this->ticket($group, 'A-002', '2026-06-18 09:01:00');

        $window = Window::factory()->for($office)->create();
        $window->queueGroups()->attach($group->id);

        $this->routing->assignNext($window);
        $next = $this->routing->skip($window);

        $this->assertSame(TicketStatus::Skipped, $first->refresh()->status);
        $this->assertSame($second->id, $next?->id);
        $this->assertSame(TicketStatus::Serving, $next?->status);
    }

    public function test_skip_returns_null_when_no_more_tickets(): void
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);
        $this->ticket($group, 'A-001', '2026-06-18 09:00:00');

        $window = Window::factory()->for($office)->create();
        $window->queueGroups()->attach($group->id);

        $this->routing->assignNext($window);

        $this->assertNull($this->routing->skip($window));
    }

    public function test_recall_returns_current_ticket_without_state_change(): void
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);
        $this->ticket($group, 'A-001', '2026-06-18 09:00:00');

        $window = Window::factory()->for($office)->create();
        $window->queueGroups()->attach($group->id);

        $assignment = $this->routing->assignNext($window);
        $recalled = $this->routing->recall($window);

        $this->assertSame($assignment?->ticket_id, $recalled?->id);
        $this->assertSame(TicketStatus::Serving, $recalled?->status);
        $this->assertNull($assignment?->refresh()->served_at);
    }

    public function test_serve_returns_null_when_no_open_assignment(): void
    {
        $office = Office::factory()->create();
        $window = Window::factory()->for($office)->create();

        $this->assertNull($this->routing->serve($window));
        $this->assertNull($this->routing->recall($window));
    }
}
