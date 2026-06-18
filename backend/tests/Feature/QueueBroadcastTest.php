<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\QueueUpdated;
use App\Events\TicketCalled;
use App\Enums\TicketStatus;
use App\Models\Office;
use App\Models\QueueGroup;
use App\Models\QueueTicket;
use App\Models\Service;
use App\Models\User;
use App\Models\Window;
use App\Services\QueueService;
use App\Services\RoutingService;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Task 019: assert TicketCalled & QueueUpdated dispatch on the right channels
 * with the right payload at the right moments (assign / serve / skip / recall /
 * join / leave). Broadcasting is faked — no Reverb server is required.
 */
class QueueBroadcastTest extends TestCase
{
    use RefreshDatabase;

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

    private function ticket(QueueGroup $group, string $number, string $joinedAt, ?User $user = null): QueueTicket
    {
        $service = Service::factory()->forQueueGroup($group)->create();

        return QueueTicket::factory()
            ->for($group)
            ->for($service)
            ->for($user ?? User::factory()->student())
            ->create([
                'ticket_number' => $number,
                'status' => TicketStatus::Waiting,
                'joined_at' => $joinedAt,
            ]);
    }

    public function test_assign_broadcasts_ticket_called_on_student_and_board_channels(): void
    {
        Event::fake([TicketCalled::class, QueueUpdated::class]);

        [$window, $group] = $this->windowWithGroup();
        $student = User::factory()->student()->create();
        $ticket = $this->ticket($group, 'A-001', '2026-06-18 09:00:00', $student);

        $officeId = $group->office_id;

        app(RoutingService::class)->assignNext($window);

        Event::assertDispatched(TicketCalled::class, function (TicketCalled $event) use ($student, $group, $officeId): bool {
            $channels = array_map(
                static fn (PrivateChannel $c): string => $c->name,
                $event->broadcastOn(),
            );

            $payload = $event->broadcastWith();

            return $event->broadcastAs() === 'ticket.called'
                && in_array("private-user.{$student->id}", $channels, true)
                && in_array("private-queue-group.{$group->id}", $channels, true)
                && in_array("private-office.{$officeId}", $channels, true)
                && $payload['ticket']['ticket_number'] === 'A-001'
                && $payload['window']['id'] === $event->assignment->window_id
                && is_string($payload['message']);
        });

        Event::assertDispatched(QueueUpdated::class);
    }

    public function test_serve_broadcasts_queue_updated(): void
    {
        [$window, $group] = $this->windowWithGroup();
        $this->ticket($group, 'A-001', '2026-06-18 09:00:00');

        $routing = app(RoutingService::class);
        $routing->assignNext($window);

        Event::fake([QueueUpdated::class, TicketCalled::class]);

        $routing->serve($window->refresh());

        Event::assertDispatched(QueueUpdated::class, function (QueueUpdated $event) use ($group): bool {
            $payload = $event->broadcastWith();

            return $event->broadcastAs() === 'queue.updated'
                && $payload['queue_group_id'] === $group->id
                && array_key_exists('now_serving', $payload)
                && array_key_exists('waiting_count', $payload);
        });
        Event::assertNotDispatched(TicketCalled::class);
    }

    public function test_skip_broadcasts_updated_then_calls_next(): void
    {
        [$window, $group] = $this->windowWithGroup();
        $this->ticket($group, 'A-001', '2026-06-18 09:00:00');
        $this->ticket($group, 'A-002', '2026-06-18 09:05:00');

        $routing = app(RoutingService::class);
        $routing->assignNext($window);

        Event::fake([QueueUpdated::class, TicketCalled::class]);

        $next = $routing->skip($window->refresh());

        $this->assertSame('A-002', $next?->ticket_number);
        // The skipped ticket + the new assignment each fire a board update.
        Event::assertDispatched(QueueUpdated::class);
        // The promoted ticket is announced.
        Event::assertDispatched(TicketCalled::class, function (TicketCalled $event): bool {
            return $event->broadcastWith()['ticket']['ticket_number'] === 'A-002';
        });
    }

    public function test_recall_redispatches_ticket_called_without_state_change(): void
    {
        [$window, $group] = $this->windowWithGroup();
        $this->ticket($group, 'A-001', '2026-06-18 09:00:00');

        $routing = app(RoutingService::class);
        $routing->assignNext($window);

        Event::fake([TicketCalled::class]);

        $ticket = $routing->recall($window->refresh());

        $this->assertSame(TicketStatus::Serving, $ticket?->status);
        Event::assertDispatched(TicketCalled::class, function (TicketCalled $event): bool {
            return $event->broadcastWith()['ticket']['ticket_number'] === 'A-001';
        });
    }

    public function test_join_broadcasts_queue_updated(): void
    {
        Event::fake([QueueUpdated::class]);

        [$window, $group] = $this->windowWithGroup();
        $service = Service::factory()->forQueueGroup($group)->create();
        $student = User::factory()->student()->create();

        app(QueueService::class)->join($student, new \App\DTOs\JoinQueueData(serviceId: $service->id));

        Event::assertDispatched(QueueUpdated::class, function (QueueUpdated $event) use ($group): bool {
            return $event->broadcastWith()['queue_group_id'] === $group->id;
        });
    }

    public function test_leave_broadcasts_queue_updated(): void
    {
        [$window, $group] = $this->windowWithGroup();
        $student = User::factory()->student()->create();
        $this->ticket($group, 'A-001', '2026-06-18 09:00:00', $student);

        Event::fake([QueueUpdated::class]);

        app(QueueService::class)->leave($student);

        Event::assertDispatched(QueueUpdated::class);
    }
}
