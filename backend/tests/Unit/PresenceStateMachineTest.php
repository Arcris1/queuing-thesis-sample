<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\PresenceStatus;
use App\Enums\TicketStatus;
use App\Models\Heartbeat;
use App\Models\Office;
use App\Models\QueueGroup;
use App\Models\QueueTicket;
use App\Models\Service;
use App\Services\PresenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresenceStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private PresenceService $presence;

    protected function setUp(): void
    {
        parent::setUp();
        $this->presence = app(PresenceService::class);

        config()->set('queue_system.presence.away_after_seconds', 120);
        config()->set('queue_system.presence.offline_after_seconds', 300);
        config()->set('queue_system.presence.removed_after_seconds', 600);
    }

    private function ticket(): QueueTicket
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);
        $service = Service::factory()->forQueueGroup($group)->create();

        return QueueTicket::factory()
            ->for($group)
            ->for($service)
            ->create([
                'status' => TicketStatus::Waiting,
                'joined_at' => now(),
            ]);
    }

    private function heartbeatAt(QueueTicket $ticket, string $when): void
    {
        Heartbeat::factory()->create([
            'user_id' => $ticket->user_id,
            'ticket_id' => $ticket->id,
            'last_seen' => now()->parse($when),
        ]);
    }

    public function test_presence_transitions_across_each_threshold(): void
    {
        $base = '2026-06-18 09:00:00';
        $ticket = $this->ticket();
        $this->heartbeatAt($ticket, $base);

        // <2m → Active
        $this->travelTo('2026-06-18 09:01:00');
        $this->assertSame(PresenceStatus::Active, $this->presence->evaluate($ticket->fresh()));

        // >2m → Away
        $this->travelTo('2026-06-18 09:03:00');
        $this->assertSame(PresenceStatus::Away, $this->presence->evaluate($ticket->fresh()));

        // >5m → Offline
        $this->travelTo('2026-06-18 09:06:00');
        $this->assertSame(PresenceStatus::Offline, $this->presence->evaluate($ticket->fresh()));

        // >10m → Removed
        $this->travelTo('2026-06-18 09:11:00');
        $this->assertSame(PresenceStatus::Removed, $this->presence->evaluate($ticket->fresh()));

        $this->travelBack();
    }

    public function test_ticket_with_no_heartbeat_is_treated_as_active(): void
    {
        $ticket = $this->ticket();

        $this->assertSame(PresenceStatus::Active, $this->presence->evaluate($ticket));
    }

    public function test_reclaim_removes_stale_waiting_ticket_and_leaves_fresh_one(): void
    {
        $this->travelTo('2026-06-18 09:00:00');

        $stale = $this->ticket();
        $this->heartbeatAt($stale, '2026-06-18 09:00:00');

        $fresh = $this->ticket();
        $this->heartbeatAt($fresh, '2026-06-18 09:00:00');

        // 11 minutes later the first heartbeat is past the removed threshold; we
        // refresh the second ticket's heartbeat so it stays Active.
        $this->travelTo('2026-06-18 09:11:00');
        $this->heartbeatAt($fresh, '2026-06-18 09:11:00');

        $reclaimed = $this->presence->reclaimAbandoned();

        $this->assertSame(1, $reclaimed);
        $this->assertSame(TicketStatus::Skipped, $stale->fresh()->status);
        $this->assertSame(TicketStatus::Waiting, $fresh->fresh()->status);

        $this->travelBack();
    }

    public function test_reclaim_is_idempotent(): void
    {
        $this->travelTo('2026-06-18 09:00:00');

        $stale = $this->ticket();
        $this->heartbeatAt($stale, '2026-06-18 09:00:00');

        $this->travelTo('2026-06-18 09:11:00');

        $this->assertSame(1, $this->presence->reclaimAbandoned());
        // Second run changes nothing — the ticket is already out of line.
        $this->assertSame(0, $this->presence->reclaimAbandoned());
        $this->assertSame(TicketStatus::Skipped, $stale->fresh()->status);

        $this->travelBack();
    }

    public function test_heartbeat_model_accessor_uses_the_centralized_rule(): void
    {
        $this->travelTo('2026-06-18 09:00:00');
        $ticket = $this->ticket();
        $this->heartbeatAt($ticket, '2026-06-18 09:00:00');

        $this->travelTo('2026-06-18 09:06:00');

        /** @var Heartbeat $heartbeat */
        $heartbeat = $ticket->latestHeartbeat()->first();
        $this->assertSame(PresenceStatus::Offline, $heartbeat->presence_status);

        $this->travelBack();
    }
}
