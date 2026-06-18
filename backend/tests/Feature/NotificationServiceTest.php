<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTOs\PushMessageData;
use App\Enums\NotificationType;
use App\Models\Office;
use App\Models\QueueGroup;
use App\Models\QueueTicket;
use App\Models\Service;
use App\Models\User;
use App\Models\Window;
use App\Notifications\Contracts\PushSender;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 020: NotificationService persists a `push_notifications` row per send and
 * hands a PushMessageData to the (fake) transport. FCM is never required.
 */
class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, PushMessageData>
     */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->sent = [];

        // Swap the bound PushSender for an in-memory spy — no log, no FCM.
        $this->app->instance(PushSender::class, new class($this->sent) implements PushSender
        {
            /**
             * @param  array<int, PushMessageData>  $sink
             */
            public function __construct(private array &$sink) {}

            public function send(PushMessageData $message): void
            {
                $this->sink[] = $message;
            }
        });
    }

    private function ticketFor(User $user): QueueTicket
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create(['prefix' => 'A']);
        $service = Service::factory()->forQueueGroup($group)->create();

        return QueueTicket::factory()
            ->for($group)
            ->for($service)
            ->for($user)
            ->create(['ticket_number' => 'A-007']);
    }

    private function service(): NotificationService
    {
        return app(NotificationService::class);
    }

    public function test_proceed_persists_row_and_sends(): void
    {
        $user = User::factory()->student()->create(['fcm_token' => 'token-123']);
        $ticket = $this->ticketFor($user);
        $window = Window::factory()->for($ticket->queueGroup->office)->create(['name' => 'Window 3']);

        $record = $this->service()->proceed($ticket, $window);

        $this->assertSame(NotificationType::Proceed, $record->type);
        $this->assertDatabaseHas('push_notifications', [
            'user_id' => $user->id,
            'type' => NotificationType::Proceed->value,
        ]);
        $this->assertNotNull($record->sent_at);

        $this->assertCount(1, $this->sent);
        $this->assertSame($user->id, $this->sent[0]->userId);
        $this->assertSame('token-123', $this->sent[0]->fcmToken);
        $this->assertStringContainsString('Window 3', $this->sent[0]->body);
    }

    public function test_position_milestone_persists_row(): void
    {
        $user = User::factory()->student()->create();
        $ticket = $this->ticketFor($user);

        $record = $this->service()->positionMilestone($ticket, peopleAhead: 5);

        $this->assertSame(NotificationType::PositionUpdate, $record->type);
        $this->assertStringContainsString('#6', $record->message);
        $this->assertDatabaseHas('push_notifications', [
            'user_id' => $user->id,
            'type' => NotificationType::PositionUpdate->value,
        ]);
        $this->assertCount(1, $this->sent);
    }

    public function test_eta_update_uses_supplied_minutes(): void
    {
        $user = User::factory()->student()->create();
        $ticket = $this->ticketFor($user);

        $record = $this->service()->etaUpdate($ticket, minutes: 12);

        $this->assertSame(NotificationType::EtaUpdate, $record->type);
        $this->assertStringContainsString('12', $record->message);
    }

    public function test_reconnect_warning_persists_row(): void
    {
        $user = User::factory()->student()->create();
        $ticket = $this->ticketFor($user);

        $record = $this->service()->reconnectWarning($ticket);

        $this->assertSame(NotificationType::ReconnectWarning, $record->type);
        $this->assertCount(1, $this->sent);
    }

    public function test_missing_token_is_handled_without_error(): void
    {
        $user = User::factory()->student()->create(['fcm_token' => null]);
        $ticket = $this->ticketFor($user);

        $record = $this->service()->reconnectWarning($ticket);

        // Row still persisted (durable history) and the send was attempted with a
        // null token — the transport tolerates it.
        $this->assertDatabaseHas('push_notifications', ['id' => $record->id]);
        $this->assertCount(1, $this->sent);
        $this->assertNull($this->sent[0]->fcmToken);
    }

    public function test_eta_placeholder_estimate_is_people_ahead_times_avg(): void
    {
        config()->set('queue_system.notifications.avg_service_minutes', 4);

        $this->assertSame(20, $this->service()->estimateMinutes(5));
        $this->assertSame(0, $this->service()->estimateMinutes(0));
    }

    public function test_crossed_milestone_debounce(): void
    {
        config()->set('queue_system.notifications.position_milestones', [10, 5, 3, 1]);

        $service = $this->service();

        $this->assertTrue($service->crossedMilestone(6, 5));   // crossed into 5
        $this->assertFalse($service->crossedMilestone(6, 4));  // 4 is not a milestone
        $this->assertFalse($service->crossedMilestone(5, 5));  // no movement
        $this->assertFalse($service->crossedMilestone(2, 4));  // moved backwards
    }
}
