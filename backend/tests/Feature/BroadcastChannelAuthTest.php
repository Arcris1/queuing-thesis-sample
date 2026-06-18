<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Office;
use App\Models\QueueGroup;
use App\Models\User;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use ReflectionClass;
use Tests\TestCase;

/**
 * Task 018: channel authorization rules. We invoke the registered channel
 * callbacks directly (the closures routes/channels.php registers via
 * Broadcast::channel) so the assertions test the authorization LOGIC and are
 * independent of the broadcast driver: a truthy return authorizes, falsy denies.
 */
class BroadcastChannelAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_channel_authorizes_owner_only(): void
    {
        $owner = User::factory()->student()->create();
        $other = User::factory()->student()->create();

        $this->assertTrue($this->authorize('user.{id}', $owner, [$owner->id]));
        $this->assertFalse($this->authorize('user.{id}', $other, [$owner->id]));
    }

    public function test_board_channels_are_staff_gated(): void
    {
        $office = Office::factory()->create();
        $group = QueueGroup::factory()->for($office)->create();

        $student = User::factory()->student()->create();
        $staff = User::factory()->staff()->create();
        $admin = User::factory()->admin()->create();

        // Queue-group board.
        $this->assertFalse($this->authorize('queue-group.{queueGroup}', $student, [$group->id]));
        $this->assertTrue($this->authorize('queue-group.{queueGroup}', $staff, [$group->id]));

        // A non-existent group is rejected even for staff.
        $this->assertFalse($this->authorize('queue-group.{queueGroup}', $staff, [99999]));

        // Office board.
        $this->assertFalse($this->authorize('office.{office}', $student, [$office->id]));
        $this->assertTrue($this->authorize('office.{office}', $admin, [$office->id]));
    }

    /**
     * Resolve the closure registered for $pattern in routes/channels.php and run
     * it as the broadcaster would: ($user, ...$args).
     *
     * @param  array<int, int>  $args
     */
    private function authorize(string $pattern, User $user, array $args): bool
    {
        /** @var Broadcaster $broadcaster */
        $broadcaster = Broadcast::driver();

        $channels = (new ReflectionClass($broadcaster))->getProperty('channels');
        /** @var array<string, callable> $registered */
        $registered = $channels->getValue($broadcaster);

        $this->assertArrayHasKey($pattern, $registered, "Channel {$pattern} is not registered.");

        return (bool) $registered[$pattern]($user, ...$args);
    }
}
