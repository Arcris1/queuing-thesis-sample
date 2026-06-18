<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\TicketStatus;
use App\Models\LocationLog;
use App\Models\Office;
use App\Models\QueueGroup;
use App\Models\QueueTicket;
use App\Models\Service;
use App\Models\Window;
use App\Services\RoutingService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutingGeofenceEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private RoutingService $routing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->routing = app(RoutingService::class);

        config()->set('queue_system.geofence.max_age_seconds', 120);
        config()->set('queue_system.geofence.require_location', false);
    }

    /**
     * Office at the plan §8 coordinates with a 15 m radius.
     */
    private function office(): Office
    {
        return Office::factory()->create([
            'latitude' => 14.600100,
            'longitude' => 121.050100,
            'geofence_radius_m' => 15,
        ]);
    }

    private function ticket(QueueGroup $group, string $number, string $joinedAt): QueueTicket
    {
        $service = Service::factory()->forQueueGroup($group)->create();

        return QueueTicket::factory()
            ->for($group)
            ->for($service)
            ->create([
                'ticket_number' => $number,
                'status' => TicketStatus::Waiting,
                'joined_at' => $joinedAt,
            ]);
    }

    private function locate(QueueTicket $ticket, float $lat, float $lng, ?CarbonInterface $at = null): void
    {
        LocationLog::factory()->create([
            'user_id' => $ticket->user_id,
            'ticket_id' => $ticket->id,
            'latitude' => $lat,
            'longitude' => $lng,
            'distance_m' => 0,
            'recorded_at' => $at ?? now(),
        ]);
    }

    public function test_out_of_range_ticket_is_skipped_for_an_in_range_one(): void
    {
        $office = $this->office();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);

        $outOfRange = $this->ticket($group, 'A-001', '2026-06-18 09:00:00');
        $inRange = $this->ticket($group, 'A-002', '2026-06-18 09:01:00');

        // Oldest ticket has a fresh BUT far-away sample.
        $this->locate($outOfRange, 14.605000, 121.055000);
        // Second ticket is fresh and ~8.4 m away → within 15 m.
        $this->locate($inRange, 14.600120, 121.050130);

        $window = Window::factory()->for($office)->create();
        $window->queueGroups()->attach($group->id);

        $assignment = $this->routing->assignNext($window);

        $this->assertSame($inRange->id, $assignment?->ticket_id);
        $this->assertSame(TicketStatus::Waiting, $outOfRange->refresh()->status);
    }

    public function test_in_range_oldest_ticket_is_assigned(): void
    {
        $office = $this->office();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);

        $oldest = $this->ticket($group, 'A-001', '2026-06-18 09:00:00');
        $this->locate($oldest, 14.600120, 121.050130);

        $window = Window::factory()->for($office)->create();
        $window->queueGroups()->attach($group->id);

        $this->assertSame($oldest->id, $this->routing->assignNext($window)?->ticket_id);
    }

    public function test_stale_in_range_sample_is_ignored_under_default_policy(): void
    {
        // Default policy (require_location=false): a stale sample counts as "no
        // signal", so the ticket falls back to eligible (best-effort).
        $office = $this->office();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);

        $ticket = $this->ticket($group, 'A-001', '2026-06-18 09:00:00');
        // In range but recorded well past the 120s TTL.
        $this->locate($ticket, 14.600120, 121.050130, now()->subMinutes(10));

        $window = Window::factory()->for($office)->create();
        $window->queueGroups()->attach($group->id);

        $this->assertSame($ticket->id, $this->routing->assignNext($window)?->ticket_id);
    }

    public function test_no_log_ticket_is_eligible_under_default_policy(): void
    {
        $office = $this->office();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);

        $ticket = $this->ticket($group, 'A-001', '2026-06-18 09:00:00');

        $window = Window::factory()->for($office)->create();
        $window->queueGroups()->attach($group->id);

        $this->assertSame($ticket->id, $this->routing->assignNext($window)?->ticket_id);
    }

    public function test_strict_policy_requires_an_in_range_sample(): void
    {
        config()->set('queue_system.geofence.require_location', true);

        $office = $this->office();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);

        // No location sample at all → ineligible under strict policy.
        $this->ticket($group, 'A-001', '2026-06-18 09:00:00');

        $window = Window::factory()->for($office)->create();
        $window->queueGroups()->attach($group->id);

        $this->assertNull($this->routing->assignNext($window));
    }
}
