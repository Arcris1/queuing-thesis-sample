<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Office;
use App\Models\QueueGroup;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $queueGroup = QueueGroup::factory();

        return [
            'office_id' => Office::factory(),
            'queue_group_id' => $queueGroup,
            'name' => fake()->words(2, true),
            'avg_service_minutes' => fake()->numberBetween(2, 15),
        ];
    }

    public function forQueueGroup(QueueGroup $queueGroup): static
    {
        return $this->state(fn (array $attributes): array => [
            'office_id' => $queueGroup->office_id,
            'queue_group_id' => $queueGroup->id,
        ]);
    }
}
