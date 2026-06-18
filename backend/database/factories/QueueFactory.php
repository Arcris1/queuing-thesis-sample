<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\QueueStatus;
use App\Models\Office;
use App\Models\Queue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Queue>
 */
class QueueFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'office_id' => Office::factory(),
            'date' => today(),
            'status' => QueueStatus::Open,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => QueueStatus::Closed,
        ]);
    }
}
