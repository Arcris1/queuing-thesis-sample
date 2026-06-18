<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Enums\WindowStatus;
use App\Models\Office;
use App\Models\QueueGroup;
use App\Models\QueueTicket;
use App\Models\Service;
use App\Models\ServiceHistory;
use App\Models\Window;
use App\Services\RoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Task 022: completing a ticket on serve writes exactly one service_history row
 * with the features the AI model trains on (duration from called_at→served_at,
 * day/hour, active windows serving the group).
 */
class ServiceHistoryCaptureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Window, 1: QueueGroup, 2: Service}
     */
    private function context(): array
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);
        $service = Service::factory()->forQueueGroup($group)->create(['avg_service_minutes' => 4]);
        $window = Window::factory()->for($office)->create(['status' => WindowStatus::Open]);
        $window->queueGroups()->attach($group->id);

        return [$window, $group, $service];
    }

    public function test_serve_writes_a_service_history_row(): void
    {
        Carbon::setTestNow('2026-06-18 10:00:00'); // Thursday (dayOfWeek = 4)
        [$window, $group, $service] = $this->context();

        $ticket = QueueTicket::factory()
            ->for($group)
            ->for($service)
            ->create([
                'status' => TicketStatus::Serving,
                'called_at' => now()->subMinutes(7),
                'ticket_number' => 'A-001',
            ]);

        $window->assignments()->create([
            'ticket_id' => $ticket->id,
            'assigned_at' => now()->subMinutes(7),
            'served_at' => null,
        ]);

        app(RoutingService::class)->serve($window);

        $this->assertDatabaseCount('service_history', 1);

        /** @var ServiceHistory $row */
        $row = ServiceHistory::query()->firstOrFail();

        $this->assertSame($group->office_id, $row->office_id);
        $this->assertSame($group->id, $row->queue_group_id);
        $this->assertSame($service->id, $row->service_id);
        $this->assertSame($window->id, $row->window_id);
        $this->assertSame(7.0, (float) $row->duration_minutes);
        $this->assertSame(10, $row->hour_of_day);
        $this->assertSame(4, $row->day_of_week);
        // One open window attached to the group → counted as 1 active window.
        $this->assertSame(1, $row->active_windows);

        Carbon::setTestNow();
    }

    public function test_serve_floors_duration_to_one_minute_when_called_at_is_null(): void
    {
        [$window, $group, $service] = $this->context();

        $ticket = QueueTicket::factory()
            ->for($group)
            ->for($service)
            ->create([
                'status' => TicketStatus::Serving,
                'called_at' => null,
                'ticket_number' => 'A-002',
            ]);

        $window->assignments()->create([
            'ticket_id' => $ticket->id,
            'assigned_at' => now(),
            'served_at' => null,
        ]);

        app(RoutingService::class)->serve($window);

        /** @var ServiceHistory $row */
        $row = ServiceHistory::query()->firstOrFail();
        $this->assertSame(1.0, (float) $row->duration_minutes);
    }

    public function test_no_history_row_when_window_has_no_open_assignment(): void
    {
        [$window] = $this->context();

        app(RoutingService::class)->serve($window);

        $this->assertDatabaseCount('service_history', 0);
    }
}
