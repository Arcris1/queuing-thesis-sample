<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\QueueTicket;
use App\Models\Window;
use App\Models\WindowAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WindowAssignment>
 */
class WindowAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'window_id' => Window::factory(),
            'ticket_id' => QueueTicket::factory(),
            'assigned_at' => now(),
            'served_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'served_at' => now(),
        ]);
    }
}
