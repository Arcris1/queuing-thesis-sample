<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Office>
 */
class OfficeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Office',
            'latitude' => fake()->latitude(14.6000, 14.6010),
            'longitude' => fake()->longitude(121.0500, 121.0510),
            'geofence_radius_m' => 15,
        ];
    }
}
