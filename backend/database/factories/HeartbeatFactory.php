<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Heartbeat;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Heartbeat>
 */
class HeartbeatFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ticket_id' => null,
            'last_seen' => now(),
            'battery_level' => fake()->numberBetween(5, 100),
            'network_status' => fake()->randomElement(['wifi', 'cellular', 'offline']),
        ];
    }
}
