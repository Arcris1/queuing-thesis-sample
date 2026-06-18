<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LocationLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LocationLog>
 */
class LocationLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ticket_id' => null,
            'latitude' => fake()->latitude(14.6000, 14.6010),
            'longitude' => fake()->longitude(121.0500, 121.0510),
            'distance_m' => fake()->randomFloat(2, 0, 100),
            'recorded_at' => now(),
        ];
    }
}
