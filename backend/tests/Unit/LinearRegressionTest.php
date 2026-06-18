<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\LinearRegression;
use PHPUnit\Framework\TestCase;

/**
 * Task 023: the pure-PHP OLS solver must recover known coefficients on a tiny
 * deterministic dataset (within tolerance) and report sane R²/RMSE.
 */
class LinearRegressionTest extends TestCase
{
    public function test_recovers_known_coefficients_on_a_noise_free_dataset(): void
    {
        // y = 2 + 3·x1 + 1·x2 exactly. Design rows carry the intercept column.
        $coefficients = [2.0, 3.0, 1.0];
        $samples = [
            [1.0, 0.0, 0.0],
            [1.0, 1.0, 0.0],
            [1.0, 0.0, 1.0],
            [1.0, 2.0, 1.0],
            [1.0, 3.0, 2.0],
            [1.0, 4.0, 0.0],
            [1.0, 1.0, 5.0],
        ];

        $y = array_map(
            static fn (array $row): float => LinearRegression::predict($row, $coefficients),
            $samples,
        );

        $fitted = LinearRegression::fit($samples, $y, ridge: 0.0);

        $this->assertEqualsWithDelta(2.0, $fitted[0], 1e-6);
        $this->assertEqualsWithDelta(3.0, $fitted[1], 1e-6);
        $this->assertEqualsWithDelta(1.0, $fitted[2], 1e-6);
    }

    public function test_r_squared_is_one_for_a_perfect_fit(): void
    {
        $actual = [3.0, 5.0, 8.0, 10.0];
        $predicted = [3.0, 5.0, 8.0, 10.0];

        $this->assertEqualsWithDelta(1.0, LinearRegression::rSquared($actual, $predicted), 1e-9);
        $this->assertEqualsWithDelta(0.0, LinearRegression::rmse($actual, $predicted), 1e-9);
    }

    public function test_rmse_measures_average_error(): void
    {
        // Errors of 2 and 2 ⇒ RMSE = 2.
        $actual = [10.0, 20.0];
        $predicted = [12.0, 18.0];

        $this->assertEqualsWithDelta(2.0, LinearRegression::rmse($actual, $predicted), 1e-9);
    }

    public function test_ridge_keeps_a_collinear_system_solvable(): void
    {
        // x2 is a perfect copy of x1 — singular without ridge. Fit must not crash
        // and must still reproduce the targets.
        $samples = [
            [1.0, 1.0, 1.0],
            [1.0, 2.0, 2.0],
            [1.0, 3.0, 3.0],
            [1.0, 4.0, 4.0],
        ];
        $y = [5.0, 8.0, 11.0, 14.0]; // y = 2 + 3·x (split arbitrarily across x1/x2)

        $fitted = LinearRegression::fit($samples, $y, ridge: 1e-8);
        $predicted = array_map(
            static fn (array $row): float => LinearRegression::predict($row, $fitted),
            $samples,
        );

        $this->assertEqualsWithDelta(1.0, LinearRegression::rSquared($y, $predicted), 1e-3);
    }
}
