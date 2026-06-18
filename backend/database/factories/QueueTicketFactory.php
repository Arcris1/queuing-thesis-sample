<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TicketStatus;
use App\Models\Queue;
use App\Models\QueueGroup;
use App\Models\QueueTicket;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QueueTicket>
 */
class QueueTicketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $queueGroup = QueueGroup::factory();

        return [
            'queue_id' => Queue::factory(),
            'queue_group_id' => $queueGroup,
            'service_id' => Service::factory(),
            'user_id' => User::factory(),
            'ticket_number' => fake()->bothify('?-###'),
            'status' => TicketStatus::Waiting,
            'priority' => 0,
            'joined_at' => now(),
            'called_at' => null,
            'served_at' => null,
        ];
    }

    public function waiting(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TicketStatus::Waiting,
        ]);
    }

    public function serving(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TicketStatus::Serving,
            'called_at' => now(),
        ]);
    }

    public function served(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TicketStatus::Served,
            'called_at' => now()->subMinutes(5),
            'served_at' => now(),
        ]);
    }

    public function priority(int $priority = 1): static
    {
        return $this->state(fn (array $attributes): array => [
            'priority' => $priority,
        ]);
    }
}
