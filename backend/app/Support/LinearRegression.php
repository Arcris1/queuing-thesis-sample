<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Pure-PHP ordinary least squares (multiple linear regression) via the normal
 * equations (plan §10, task 023). No external math library, no Python — the
 * whole training pipeline is self-contained in Laravel so it is reproducible
 * and defensible for the thesis.
 *
 * Solves  β = (Xᵀ X)⁻¹ Xᵀ y  by building the (XᵀX | Xᵀy) augmented system and
 * Gauss–Jordan eliminating with partial pivoting. A tiny ridge term (λ) on the
 * diagonal keeps XᵀX invertible when one-hot columns are collinear (e.g. a
 * service that only ever appears with one office), which would otherwise make
 * the system singular.
 *
 * Stateless: every method is static and operates only on its arguments.
 */
final class LinearRegression
{
    /**
     * Fit OLS coefficients for the design matrix X (rows = samples, each row
     * already including any intercept/one-hot columns) against targets y.
     *
     * @param  array<int, array<int, float>>  $x  n×p design matrix
     * @param  array<int, float>              $y  n targets
     * @param  float                          $ridge  L2 penalty added to the XᵀX diagonal
     * @return array<int, float>  p coefficients aligned to the columns of X
     */
    public static function fit(array $x, array $y, float $ridge = 1e-8): array
    {
        $n = count($x);

        if ($n === 0) {
            throw new RuntimeException('Cannot fit a regression on zero samples.');
        }

        $p = count($x[0]);

        // Normal equations: A = XᵀX (+ ridge·I), b = Xᵀy.
        $a = array_fill(0, $p, array_fill(0, $p, 0.0));
        $b = array_fill(0, $p, 0.0);

        foreach ($x as $i => $row) {
            $yi = $y[$i];

            for ($j = 0; $j < $p; $j++) {
                $b[$j] += $row[$j] * $yi;

                for ($k = $j; $k < $p; $k++) {
                    $a[$j][$k] += $row[$j] * $row[$k];
                }
            }
        }

        // Mirror the symmetric upper triangle and apply the ridge.
        for ($j = 0; $j < $p; $j++) {
            $a[$j][$j] += $ridge;

            for ($k = $j + 1; $k < $p; $k++) {
                $a[$k][$j] = $a[$j][$k];
            }
        }

        return self::solve($a, $b);
    }

    /**
     * Predict a single target from a design row and fitted coefficients (dot product).
     *
     * @param  array<int, float>  $row
     * @param  array<int, float>  $coefficients
     */
    public static function predict(array $row, array $coefficients): float
    {
        $sum = 0.0;

        foreach ($row as $j => $value) {
            $sum += $value * ($coefficients[$j] ?? 0.0);
        }

        return $sum;
    }

    /**
     * Coefficient of determination (R²) of predictions against actuals on a
     * holdout. Returns 0.0 when the actuals have no variance (a degenerate set
     * where R² is undefined) so callers get a safe, bounded number.
     *
     * @param  array<int, float>  $actual
     * @param  array<int, float>  $predicted
     */
    public static function rSquared(array $actual, array $predicted): float
    {
        $n = count($actual);

        if ($n === 0) {
            return 0.0;
        }

        $mean = array_sum($actual) / $n;
        $ssTot = 0.0;
        $ssRes = 0.0;

        foreach ($actual as $i => $y) {
            $ssTot += ($y - $mean) ** 2;
            $ssRes += ($y - $predicted[$i]) ** 2;
        }

        if ($ssTot <= 0.0) {
            return 0.0;
        }

        return 1.0 - ($ssRes / $ssTot);
    }

    /**
     * Root mean squared error of predictions against actuals.
     *
     * @param  array<int, float>  $actual
     * @param  array<int, float>  $predicted
     */
    public static function rmse(array $actual, array $predicted): float
    {
        $n = count($actual);

        if ($n === 0) {
            return 0.0;
        }

        $sum = 0.0;

        foreach ($actual as $i => $y) {
            $sum += ($y - $predicted[$i]) ** 2;
        }

        return sqrt($sum / $n);
    }

    /**
     * Solve the linear system A·x = b by Gauss–Jordan elimination with partial
     * pivoting. A is destroyed in place (a local copy is used).
     *
     * @param  array<int, array<int, float>>  $a  p×p coefficient matrix
     * @param  array<int, float>              $b  p right-hand side
     * @return array<int, float>  p solution vector
     */
    private static function solve(array $a, array $b): array
    {
        $p = count($b);

        for ($col = 0; $col < $p; $col++) {
            // Partial pivot: swap in the row with the largest |value| in this column.
            $pivotRow = $col;
            $max = abs($a[$col][$col]);

            for ($r = $col + 1; $r < $p; $r++) {
                if (abs($a[$r][$col]) > $max) {
                    $max = abs($a[$r][$col]);
                    $pivotRow = $r;
                }
            }

            if ($max < 1e-12) {
                // Singular even after ridge — treat this coefficient as 0.
                continue;
            }

            if ($pivotRow !== $col) {
                [$a[$col], $a[$pivotRow]] = [$a[$pivotRow], $a[$col]];
                [$b[$col], $b[$pivotRow]] = [$b[$pivotRow], $b[$col]];
            }

            $pivot = $a[$col][$col];

            // Normalize the pivot row.
            for ($j = $col; $j < $p; $j++) {
                $a[$col][$j] /= $pivot;
            }
            $b[$col] /= $pivot;

            // Eliminate this column from every other row.
            for ($r = 0; $r < $p; $r++) {
                if ($r === $col) {
                    continue;
                }

                $factor = $a[$r][$col];

                if ($factor === 0.0) {
                    continue;
                }

                for ($j = $col; $j < $p; $j++) {
                    $a[$r][$j] -= $factor * $a[$col][$j];
                }

                $b[$r] -= $factor * $b[$col];
            }
        }

        return $b;
    }
}
