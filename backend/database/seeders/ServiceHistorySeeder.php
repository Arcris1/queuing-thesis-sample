<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceHistory;
use App\Models\Window;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * SYNTHETIC service_history generator (task 022, plan §10).
 *
 * Real history is thin until the system has run for months, so for training /
 * the defense we fabricate a realistic-but-CLEARLY-SYNTHETIC dataset. Each row
 * is built from a service's true avg_service_minutes with plausible structure
 * the regression can actually learn:
 *
 *   - per-service base duration (Assessment ~4, Transcript ~8, Refund ~10, …)
 *   - time-of-day effect: peak hours (10–11, 13–14) run ~30% slower
 *   - day-of-week effect: Mondays (1) run slower, weekends lighter
 *   - active-windows spread: 1–3 open windows, more windows ⇒ (implicitly) faster
 *     group clearance; recorded so the model sees the capacity signal
 *   - gaussian noise so it is not perfectly separable
 *
 * Volume is configurable; default ~1500 rows spread over the last 4 months.
 *
 * This data is FABRICATED for model bootstrapping/demo only — it is not real
 * transaction history.
 */
class ServiceHistorySeeder extends Seeder
{
    public function run(int $count = 1500): void
    {
        /** @var Collection<int, Service> $services */
        $services = Service::query()->with('queueGroup')->get();

        if ($services->isEmpty()) {
            $this->command?->warn('No services found — run OfficeServiceSeeder first.');

            return;
        }

        // Window ids per queue group, to assign a plausible serving window.
        /** @var array<int, array<int, int>> $windowsByGroup */
        $windowsByGroup = [];
        Window::query()->with('queueGroups:id')->get()->each(function (Window $window) use (&$windowsByGroup): void {
            foreach ($window->queueGroups as $group) {
                $windowsByGroup[$group->id][] = $window->id;
            }
        });

        $rows = [];
        $now = Carbon::now();

        for ($i = 0; $i < $count; $i++) {
            /** @var Service $service */
            $service = $services->random();
            $groupId = (int) $service->queue_group_id;

            $servedAt = $now->copy()->subSeconds(random_int(0, 4 * 30 * 24 * 3600));
            $hour = (int) $servedAt->hour;
            $day = (int) $servedAt->dayOfWeek;

            $activeWindows = $this->plausibleActiveWindows($windowsByGroup[$groupId] ?? []);

            $rows[] = [
                'office_id' => $service->office_id,
                'queue_group_id' => $groupId,
                'service_id' => $service->id,
                'window_id' => isset($windowsByGroup[$groupId])
                    ? $windowsByGroup[$groupId][array_rand($windowsByGroup[$groupId])]
                    : null,
                'served_at' => $servedAt,
                'duration_minutes' => $this->syntheticDuration(
                    (float) $service->avg_service_minutes,
                    $hour,
                    $day,
                    $activeWindows,
                ),
                'day_of_week' => $day,
                'hour_of_day' => $hour,
                'active_windows' => $activeWindows,
                'created_at' => $servedAt,
                'updated_at' => $servedAt,
            ];

            // Bulk insert in chunks to keep memory flat for large volumes.
            if (count($rows) >= 500) {
                ServiceHistory::insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            ServiceHistory::insert($rows);
        }

        $this->command?->info("Seeded synthetic service_history (~{$count} rows).");
    }

    /**
     * A duration that embeds the learnable structure: base service time × peak ×
     * day-of-week multipliers, plus gaussian noise, floored at 1 minute.
     */
    private function syntheticDuration(float $base, int $hour, int $day, int $activeWindows): float
    {
        $peak = in_array($hour, [10, 11, 13, 14], true) ? 1.3 : 1.0;
        $mondayDrag = $day === 1 ? 1.15 : 1.0;
        $weekendEase = in_array($day, [0, 6], true) ? 0.9 : 1.0;

        // More open windows correlates with slightly shorter per-call time (busier
        // groups staff more windows and keep the line tighter) — a mild signal.
        $windowEffect = 1.0 - (max(1, $activeWindows) - 1) * 0.05;

        $mean = $base * $peak * $mondayDrag * $weekendEase * $windowEffect;
        $noise = $this->gaussian() * ($base * 0.15);

        return max(1.0, round($mean + $noise, 2));
    }

    /**
     * Plausible open-window count for a group: bounded by the windows actually
     * attached, biased toward 1–2.
     *
     * @param  array<int, int>  $windowIds
     */
    private function plausibleActiveWindows(array $windowIds): int
    {
        $max = max(1, count($windowIds));

        // Weighted toward fewer windows; cap at the group's actual window count.
        $draw = match (random_int(1, 10)) {
            1, 2, 3, 4, 5 => 1,
            6, 7, 8 => 2,
            default => 3,
        };

        return min($max, $draw);
    }

    /**
     * Standard-normal sample via Box–Muller — deterministic-free, plain noise.
     */
    private function gaussian(): float
    {
        $u1 = mt_rand() / mt_getrandmax();
        $u2 = mt_rand() / mt_getrandmax();
        $u1 = max($u1, 1e-9);

        return sqrt(-2.0 * log($u1)) * cos(2.0 * M_PI * $u2);
    }
}
