<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\QueueGroupStatus;
use App\Models\Office;
use App\Models\QueueGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<QueueGroup>
 */
class QueueGroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'office_id' => Office::factory(),
            'name' => fake()->unique()->words(2, true),
            'prefix' => Str::upper(fake()->unique()->lexify('??')),
            'status' => QueueGroupStatus::Open,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => QueueGroupStatus::Closed,
        ]);
    }
}
