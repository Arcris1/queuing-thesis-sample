<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ServiceHistory;
use Database\Seeders\OfficeServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 022: ml:seed-history fabricates synthetic service_history rows with the
 * structure the regression can learn (per-service base + peak/day/window
 * effects). Asserts volume and that the features fall in valid ranges.
 */
class SeedServiceHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OfficeServiceSeeder::class);
    }

    public function test_command_generates_the_requested_number_of_rows(): void
    {
        $this->artisan('ml:seed-history', ['count' => 120])
            ->assertSuccessful();

        $this->assertSame(120, ServiceHistory::query()->count());
    }

    public function test_seeded_rows_have_valid_feature_ranges(): void
    {
        $this->artisan('ml:seed-history', ['count' => 200])->assertSuccessful();

        $rows = ServiceHistory::query()->get();

        foreach ($rows as $row) {
            $this->assertGreaterThanOrEqual(1.0, (float) $row->duration_minutes);
            $this->assertGreaterThanOrEqual(0, $row->hour_of_day);
            $this->assertLessThanOrEqual(23, $row->hour_of_day);
            $this->assertGreaterThanOrEqual(0, $row->day_of_week);
            $this->assertLessThanOrEqual(6, $row->day_of_week);
            $this->assertGreaterThanOrEqual(1, $row->active_windows);
        }
    }

    public function test_rejects_a_non_positive_count(): void
    {
        $this->artisan('ml:seed-history', ['count' => 0])
            ->assertFailed();
    }
}
