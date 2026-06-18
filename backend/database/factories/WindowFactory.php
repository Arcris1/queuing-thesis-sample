<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WindowStatus;
use App\Models\Office;
use App\Models\Window;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Window>
 */
class WindowFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'office_id' => Office::factory(),
            'name' => 'Window '.fake()->numberBetween(1, 5),
            'status' => WindowStatus::Closed,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => WindowStatus::Open,
        ]);
    }
}
