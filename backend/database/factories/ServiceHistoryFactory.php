<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Office;
use App\Models\QueueGroup;
use App\Models\Service;
use App\Models\ServiceHistory;
use App\Models\Window;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<ServiceHistory>
 */
class ServiceHistoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /** @var Carbon $servedAt */
        $servedAt = fake()->dateTimeBetween('-3 months', 'now');
        $servedAt = Carbon::instance($servedAt);

        return [
            'office_id' => Office::factory(),
            'queue_group_id' => QueueGroup::factory(),
            'service_id' => Service::factory(),
            'window_id' => Window::factory(),
            'served_at' => $servedAt,
            'duration_minutes' => fake()->randomFloat(2, 1, 20),
            'day_of_week' => (int) $servedAt->dayOfWeek,
            'hour_of_day' => (int) $servedAt->hour,
            'active_windows' => fake()->numberBetween(1, 3),
        ];
    }
}
