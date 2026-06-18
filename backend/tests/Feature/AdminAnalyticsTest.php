<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Office;
use App\Models\QueueGroup;
use App\Models\QueueTicket;
use App\Models\Service;
use App\Models\ServiceHistory;
use App\Models\User;
use App\Models\Window;
use App\Models\WindowAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function staffToken(): string
    {
        return auth('api')->login(User::factory()->staff()->create());
    }

    public function test_analytics_returns_correct_aggregates(): void
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A', 'name' => 'Accounting']);
        $service = Service::factory()->forQueueGroup($group)->create(['name' => 'Payment']);
        $window = Window::factory()->for($office)->create(['name' => 'Window 1']);

        // Three served history rows at known hours and durations:
        // durations 4, 6, 8 → avg 6.0; served = 3; peak hour 10 has 2.
        $this->history($office, $group, $service, $window, hour: 10, duration: 4.0);
        $this->history($office, $group, $service, $window, hour: 10, duration: 6.0);
        $this->history($office, $group, $service, $window, hour: 14, duration: 8.0);

        // Served tickets with known wait: joined 09:00 → called 09:10 = 10 min, and
        // 09:00 → 09:20 = 20 min → avg wait 15.0 min.
        $this->servedTicket($group, $service, '2026-06-18 09:00:00', '2026-06-18 09:10:00');
        $this->servedTicket($group, $service, '2026-06-18 09:00:00', '2026-06-18 09:20:00');

        // Missed: 1 skipped + 1 standby = 2.
        QueueTicket::factory()->for($group)->for($service)->create([
            'status' => TicketStatus::Skipped,
            'joined_at' => '2026-06-18 09:00:00',
        ]);
        QueueTicket::factory()->for($group)->for($service)->create([
            'status' => TicketStatus::Standby,
            'joined_at' => '2026-06-18 09:00:00',
        ]);

        // Window utilization: one completed assignment of 5 minutes. Its ticket is
        // Skipped (not Served) so it does not enter the served-ticket wait average.
        $utilTicket = QueueTicket::factory()->for($group)->for($service)->create([
            'status' => TicketStatus::Skipped,
            'joined_at' => '2026-06-18 08:00:00',
        ]);
        WindowAssignment::factory()->create([
            'window_id' => $window->id,
            'ticket_id' => $utilTicket->id,
            'assigned_at' => '2026-06-18 11:00:00',
            'served_at' => '2026-06-18 11:05:00',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->staffToken()}")
            ->getJson("/api/admin/analytics?office_id={$office->id}")
            ->assertOk();

        $response
            ->assertJsonPath('data.served', 3)
            ->assertJsonPath('data.missed', 3);

        $this->assertEquals(6.0, $response->json('data.avg_service_minutes'));
        $this->assertEquals(15.0, $response->json('data.avg_wait_minutes'));

        // Peak hours: hour 10 has 2 served, hour 14 has 1.
        $peak = collect($response->json('data.peak_hours'));
        $this->assertSame(2, $peak->firstWhere('hour', 10)['served']);
        $this->assertSame(1, $peak->firstWhere('hour', 14)['served']);

        // Per-service avg duration.
        $byService = collect($response->json('data.by_service'));
        $this->assertEquals(6.0, $byService->firstWhere('service_id', $service->id)['avg_service_minutes']);
        $this->assertSame(3, $byService->firstWhere('service_id', $service->id)['served']);

        // Per-queue-group avg duration.
        $byGroup = collect($response->json('data.by_queue_group'));
        $this->assertEquals(6.0, $byGroup->firstWhere('queue_group_id', $group->id)['avg_service_minutes']);

        // Window utilization: 5 busy minutes.
        $util = collect($response->json('data.window_utilization'));
        $this->assertEquals(5.0, $util->firstWhere('window_id', $window->id)['busy_minutes']);
    }

    public function test_date_range_filtering(): void
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create();
        $service = Service::factory()->forQueueGroup($group)->create();
        $window = Window::factory()->for($office)->create();

        // In range (June) and out of range (May).
        $this->history($office, $group, $service, $window, hour: 9, duration: 5.0, servedAt: '2026-06-10 09:00:00');
        $this->history($office, $group, $service, $window, hour: 9, duration: 5.0, servedAt: '2026-05-10 09:00:00');

        $this->withHeader('Authorization', "Bearer {$this->staffToken()}")
            ->getJson("/api/admin/analytics?office_id={$office->id}&from=2026-06-01&to=2026-06-30")
            ->assertOk()
            ->assertJsonPath('data.served', 1);
    }

    public function test_office_scoping(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $groupA = QueueGroup::factory()->for($officeA)->create();
        $groupB = QueueGroup::factory()->for($officeB)->create();
        $serviceA = Service::factory()->forQueueGroup($groupA)->create();
        $serviceB = Service::factory()->forQueueGroup($groupB)->create();
        $windowA = Window::factory()->for($officeA)->create();
        $windowB = Window::factory()->for($officeB)->create();

        $this->history($officeA, $groupA, $serviceA, $windowA, hour: 9, duration: 5.0);
        $this->history($officeB, $groupB, $serviceB, $windowB, hour: 9, duration: 5.0);
        $this->history($officeB, $groupB, $serviceB, $windowB, hour: 9, duration: 5.0);

        $this->withHeader('Authorization', "Bearer {$this->staffToken()}")
            ->getJson("/api/admin/analytics?office_id={$officeA->id}")
            ->assertOk()
            ->assertJsonPath('data.served', 1);
    }

    public function test_student_is_forbidden(): void
    {
        $studentToken = auth('api')->login(User::factory()->student()->create());

        $this->withHeader('Authorization', "Bearer {$studentToken}")
            ->getJson('/api/admin/analytics')
            ->assertForbidden();
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/admin/analytics')->assertUnauthorized();
    }

    private function history(
        Office $office,
        QueueGroup $group,
        Service $service,
        Window $window,
        int $hour,
        float $duration,
        string $servedAt = '2026-06-18 10:00:00',
    ): ServiceHistory {
        return ServiceHistory::factory()->create([
            'office_id' => $office->id,
            'queue_group_id' => $group->id,
            'service_id' => $service->id,
            'window_id' => $window->id,
            'served_at' => $servedAt,
            'duration_minutes' => $duration,
            'hour_of_day' => $hour,
            'day_of_week' => 1,
            'active_windows' => 1,
        ]);
    }

    private function servedTicket(QueueGroup $group, Service $service, string $joinedAt, string $calledAt): QueueTicket
    {
        return QueueTicket::factory()->for($group)->for($service)->create([
            'status' => TicketStatus::Served,
            'joined_at' => $joinedAt,
            'called_at' => $calledAt,
            'served_at' => $calledAt,
        ]);
    }
}
