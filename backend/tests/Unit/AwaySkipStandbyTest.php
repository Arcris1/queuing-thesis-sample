<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\TicketStatus;
use App\Models\Heartbeat;
use App\Models\Office;
use App\Models\QueueGroup;
use App\Models\QueueTicket;
use App\Models\Service;
use App\Models\Window;
use App\Services\PresenceService;
use App\Services\RoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AwaySkipStandbyTest extends TestCase
{
    use RefreshDatabase;

    private RoutingService $routing;

    private PresenceService $presence;

    protected function setUp(): void
    {
        parent::setUp();
        $this->routing = app(RoutingService::class);
        $this->presence = app(PresenceService::class);

        config()->set('queue_system.presence.away_after_seconds', 120);
        config()->set('queue_system.presence.offline_after_seconds', 300);
        config()->set('queue_system.presence.removed_after_seconds', 600);
        config()->set('queue_system.reconnect_grace_seconds', 120);
        config()->set('queue_system.geofence.require_location', false);
    }

    private function ticket(QueueGroup $group, string $number, string $joinedAt, ?TicketStatus $status = null): QueueTicket
    {
        $service = Service::factory()->forQueueGroup($group)->create();

        return QueueTicket::factory()
            ->for($group)
            ->for($service)
            ->create([
                'ticket_number' => $number,
                'status' => $status ?? TicketStatus::Waiting,
                'joined_at' => $joinedAt,
            ]);
    }

    private function heartbeat(QueueTicket $ticket, string $lastSeen): void
    {
        Heartbeat::factory()->create([
            'user_id' => $ticket->user_id,
            'ticket_id' => $ticket->id,
            'last_seen' => now()->parse($lastSeen),
        ]);
    }

    public function test_away_ticket_gets_grace_window_then_standby_on_expiry(): void
    {
        $this->travelTo('2026-06-18 09:00:00');

        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);
        $window = Window::factory()->for($office)->create();
        $window->queueGroups()->attach($group->id);

        // Only candidate: an Away student (last heartbeat 4 min ago).
        $away = $this->ticket($group, 'A-001', '2026-06-18 08:55:00');
        $this->heartbeat($away, '2026-06-18 08:56:00'); // 4 min stale → Away

        // First call: no eligible ticket, but Away gets a grace window opened.
        $this->assertNull($this->routing->assignNext($window));

        $away->refresh();
        $this->assertSame(TicketStatus::Waiting, $away->status, 'still in line during grace');
        $this->assertNotNull($away->grace_until);
        $this->assertNotNull($away->grace_offered_at);

        $graceOfferedAt = $away->grace_offered_at;

        // Within the grace window the warning does not re-fire (idempotent).
        $this->travelTo('2026-06-18 09:01:00');
        $this->assertNull($this->routing->assignNext($window));
        $away->refresh();
        $this->assertEquals($graceOfferedAt->toIso8601String(), $away->grace_offered_at->toIso8601String());

        // Grace elapses (>120s after it was offered) with the student still Away.
        $this->travelTo('2026-06-18 09:03:00');
        $this->assertNull($this->routing->assignNext($window));

        $away->refresh();
        $this->assertSame(TicketStatus::Standby, $away->status, 'standbyed after grace lapsed');
        $this->assertNull($away->grace_until);

        $this->travelBack();
    }

    public function test_standby_ticket_reinstates_to_waiting_on_return(): void
    {
        $this->travelTo('2026-06-18 09:00:00');

        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);

        $ticket = $this->ticket($group, 'A-001', '2026-06-18 08:55:00', TicketStatus::Standby);
        $ticket->update(['grace_until' => null, 'grace_offered_at' => now()->subMinutes(5)]);

        // Student returns: a fresh heartbeat reinstates them.
        $this->presence->reinstateOnReturn($ticket);

        $ticket->refresh();
        $this->assertSame(TicketStatus::Waiting, $ticket->status);
        $this->assertNull($ticket->grace_offered_at);
        // Original joined_at preserved so they land near their old place.
        $this->assertSame('2026-06-18 08:55:00', $ticket->joined_at->format('Y-m-d H:i:s'));

        $this->travelBack();
    }

    public function test_becoming_active_within_grace_clears_the_window_and_assigns(): void
    {
        $this->travelTo('2026-06-18 09:00:00');

        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);
        $window = Window::factory()->for($office)->create();
        $window->queueGroups()->attach($group->id);

        $student = $this->ticket($group, 'A-001', '2026-06-18 08:55:00');
        $this->heartbeat($student, '2026-06-18 08:56:00'); // Away

        // Grace opened, nobody assigned yet.
        $this->assertNull($this->routing->assignNext($window));
        $this->assertNotNull($student->refresh()->grace_until);

        // Student reconnects within grace (fresh heartbeat) → now Active.
        $this->heartbeat($student, '2026-06-18 09:01:00');

        $assignment = $this->routing->assignNext($window);

        $this->assertSame($student->id, $assignment?->ticket_id);
        $student->refresh();
        $this->assertSame(TicketStatus::Serving, $student->status);
        // Recovered within grace → window cleared, place kept.
        $this->assertNull($student->grace_until);
        $this->assertNull($student->grace_offered_at);

        $this->travelBack();
    }

    public function test_ready_ticket_is_assignable(): void
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);
        $window = Window::factory()->for($office)->create();
        $window->queueGroups()->attach($group->id);

        $ready = $this->ticket($group, 'A-001', '2026-06-18 09:00:00', TicketStatus::Ready);

        $assignment = $this->routing->assignNext($window);

        $this->assertSame($ready->id, $assignment?->ticket_id);
        $this->assertSame(TicketStatus::Serving, $ready->refresh()->status);
    }

    public function test_ready_ticket_is_preferred_over_waiting_at_equal_priority(): void
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);
        $window = Window::factory()->for($office)->create();
        $window->queueGroups()->attach($group->id);

        // Waiting joined earlier, but the Ready (checked-in, on-site) student is
        // preferred at equal priority (Ready-vs-routing resolution, task 017).
        $this->ticket($group, 'A-001', '2026-06-18 09:00:00', TicketStatus::Waiting);
        $ready = $this->ticket($group, 'A-002', '2026-06-18 09:05:00', TicketStatus::Ready);

        $assignment = $this->routing->assignNext($window);

        $this->assertSame($ready->id, $assignment?->ticket_id);
    }

    public function test_priority_still_outranks_ready(): void
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);
        $window = Window::factory()->for($office)->create();
        $window->queueGroups()->attach($group->id);

        // A high-priority Waiting ticket beats a Ready normal-priority one — priority
        // is the top sort key, Ready-vs-Waiting only breaks ties at equal priority.
        $this->ticket($group, 'A-001', '2026-06-18 09:00:00', TicketStatus::Ready);
        $priority = QueueTicket::factory()
            ->for($group)
            ->for(Service::factory()->forQueueGroup($group)->create())
            ->priority(5)
            ->create([
                'ticket_number' => 'A-002',
                'status' => TicketStatus::Waiting,
                'joined_at' => '2026-06-18 09:05:00',
            ]);

        $assignment = $this->routing->assignNext($window);

        $this->assertSame($priority->id, $assignment?->ticket_id);
    }
}
