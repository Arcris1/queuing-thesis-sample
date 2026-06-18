<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\AnalyticsFilter;
use App\DTOs\AnalyticsResult;
use App\Enums\TicketStatus;
use App\Models\QueueTicket;
use App\Models\ServiceHistory;
use App\Models\WindowAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * Aggregate analytics for the staff/admin dashboard (task 025, plan §12). Every
 * metric is computed with a SQL aggregate (AVG/COUNT/GROUP BY) — never a PHP loop
 * over loaded rows — so the queries stay indexed (tasks 005/007) and cheap even on
 * a large `service_history`.
 *
 * Sources:
 *   - `service_history` — the per-serve training/outcome table: served counts,
 *     average service duration (overall + per office/queue group/service/window),
 *     and the by-hour peak distribution.
 *   - `queue_tickets` — average WAITING time (joined_at → called_at on served
 *     tickets) and the missed-queue count (Skipped / Standby).
 *   - `window_assignments` — window utilization: busy minutes (assigned_at →
 *     served_at) per window.
 *
 * Time-difference SQL is driver-aware ({@see secondsBetween()}) so the same code
 * runs on Postgres (production) and SQLite (the test suite).
 */
final class AnalyticsService
{
    /**
     * Compute the full analytics bundle for a filter context.
     */
    public function compute(AnalyticsFilter $filter): AnalyticsResult
    {
        return new AnalyticsResult(
            avgWaitMinutes: $this->averageWaitMinutes($filter),
            avgServiceMinutes: $this->averageServiceMinutes($filter),
            served: $this->servedCount($filter),
            missed: $this->missedCount($filter),
            peakHours: $this->peakHours($filter),
            byQueueGroup: $this->avgDurationByQueueGroup($filter),
            byService: $this->avgDurationByService($filter),
            byWindow: $this->servedAndDurationByWindow($filter),
            windowUtilization: $this->windowUtilization($filter),
        );
    }

    /**
     * Average minutes a served ticket waited in line (joined_at → called_at).
     * Computed over served tickets with both timestamps present, in SQL.
     */
    private function averageWaitMinutes(AnalyticsFilter $filter): float
    {
        $query = $this->ticketScope($filter)
            ->where('status', TicketStatus::Served)
            ->whereNotNull('called_at')
            ->whereNotNull('joined_at');

        $avgSeconds = (float) $query->avg($this->secondsBetween('joined_at', 'called_at'));

        return round($avgSeconds / 60.0, 2);
    }

    /**
     * Overall average service duration in minutes from `service_history`.
     */
    private function averageServiceMinutes(AnalyticsFilter $filter): float
    {
        $avg = $this->historyScope($filter)->avg('duration_minutes');

        return round((float) $avg, 2);
    }

    /**
     * Total students served in the window — one `service_history` row per serve.
     */
    private function servedCount(AnalyticsFilter $filter): int
    {
        return $this->historyScope($filter)->count();
    }

    /**
     * Missed-queue count: tickets that left the line without being served —
     * Skipped (no-show / left / reclaimed) and Standby (grace lapsed). Counted from
     * `queue_tickets` in the filter window (plan §12 "missed-queue count").
     */
    private function missedCount(AnalyticsFilter $filter): int
    {
        return $this->ticketScope($filter)
            ->whereIn('status', [TicketStatus::Skipped, TicketStatus::Standby])
            ->count();
    }

    /**
     * Served counts grouped by hour-of-day (0–23) — the peak-hours distribution.
     * Uses the denormalized `hour_of_day` column so no timestamp extraction is
     * needed, ordered by hour.
     *
     * @return array<int, array<string, int>>
     */
    private function peakHours(AnalyticsFilter $filter): array
    {
        return $this->historyScope($filter)
            ->selectRaw('hour_of_day as hour, COUNT(*) as served')
            ->groupBy('hour_of_day')
            ->orderBy('hour_of_day')
            ->get()
            ->map(fn ($row): array => [
                'hour' => (int) $row->hour,
                'served' => (int) $row->served,
            ])
            ->all();
    }

    /**
     * Average service duration + served count per queue group.
     *
     * @return array<int, array<string, int|float|string|null>>
     */
    private function avgDurationByQueueGroup(AnalyticsFilter $filter): array
    {
        return $this->historyScope($filter)
            ->join('queue_groups', 'service_history.queue_group_id', '=', 'queue_groups.id')
            ->selectRaw('queue_groups.id as id, queue_groups.name as name, '
                .'COUNT(*) as served, AVG(service_history.duration_minutes) as avg_duration')
            ->groupBy('queue_groups.id', 'queue_groups.name')
            ->orderBy('queue_groups.name')
            ->get()
            ->map(fn ($row): array => [
                'queue_group_id' => (int) $row->id,
                'name' => (string) $row->name,
                'served' => (int) $row->served,
                'avg_service_minutes' => round((float) $row->avg_duration, 2),
            ])
            ->all();
    }

    /**
     * Average service duration + served count per service.
     *
     * @return array<int, array<string, int|float|string|null>>
     */
    private function avgDurationByService(AnalyticsFilter $filter): array
    {
        return $this->historyScope($filter)
            ->join('services', 'service_history.service_id', '=', 'services.id')
            ->selectRaw('services.id as id, services.name as name, '
                .'COUNT(*) as served, AVG(service_history.duration_minutes) as avg_duration')
            ->groupBy('services.id', 'services.name')
            ->orderBy('services.name')
            ->get()
            ->map(fn ($row): array => [
                'service_id' => (int) $row->id,
                'name' => (string) $row->name,
                'served' => (int) $row->served,
                'avg_service_minutes' => round((float) $row->avg_duration, 2),
            ])
            ->all();
    }

    /**
     * Served count + average service duration per window.
     *
     * @return array<int, array<string, int|float|string|null>>
     */
    private function servedAndDurationByWindow(AnalyticsFilter $filter): array
    {
        return $this->historyScope($filter)
            ->join('windows', 'service_history.window_id', '=', 'windows.id')
            ->selectRaw('windows.id as id, windows.name as name, '
                .'COUNT(*) as served, AVG(service_history.duration_minutes) as avg_duration')
            ->groupBy('windows.id', 'windows.name')
            ->orderBy('windows.name')
            ->get()
            ->map(fn ($row): array => [
                'window_id' => (int) $row->id,
                'name' => (string) $row->name,
                'served' => (int) $row->served,
                'avg_service_minutes' => round((float) $row->avg_duration, 2),
            ])
            ->all();
    }

    /**
     * Window utilization: total busy minutes per window from completed
     * `window_assignments` (assigned_at → served_at), with the serve count. Idle is
     * derived by the consumer against the observation window; here we report the
     * measured busy time so the resource can present utilization as busy minutes.
     *
     * @return array<int, array<string, int|float|string|null>>
     */
    private function windowUtilization(AnalyticsFilter $filter): array
    {
        $busySql = $this->secondsBetweenSql('window_assignments.assigned_at', 'window_assignments.served_at');

        $query = WindowAssignment::query()
            ->join('windows', 'window_assignments.window_id', '=', 'windows.id')
            ->whereNotNull('window_assignments.served_at')
            ->whereNotNull('window_assignments.assigned_at');

        if ($filter->officeId !== null) {
            $query->where('windows.office_id', $filter->officeId);
        }

        if ($filter->from !== null) {
            $query->where('window_assignments.served_at', '>=', $filter->from);
        }

        if ($filter->to !== null) {
            $query->where('window_assignments.served_at', '<=', $filter->to);
        }

        return $query
            ->selectRaw('windows.id as id, windows.name as name, '
                ."COUNT(*) as served, SUM({$busySql}) as busy_seconds")
            ->groupBy('windows.id', 'windows.name')
            ->orderBy('windows.name')
            ->get()
            ->map(fn ($row): array => [
                'window_id' => (int) $row->id,
                'name' => (string) $row->name,
                'served' => (int) $row->served,
                'busy_minutes' => round(((float) $row->busy_seconds) / 60.0, 2),
            ])
            ->all();
    }

    /**
     * Base `service_history` query with the office + date-range filter applied.
     *
     * @return Builder<ServiceHistory>
     */
    private function historyScope(AnalyticsFilter $filter): Builder
    {
        $query = ServiceHistory::query();

        if ($filter->officeId !== null) {
            $query->where('service_history.office_id', $filter->officeId);
        }

        if ($filter->from !== null) {
            $query->where('service_history.served_at', '>=', $filter->from);
        }

        if ($filter->to !== null) {
            $query->where('service_history.served_at', '<=', $filter->to);
        }

        return $query;
    }

    /**
     * Base `queue_tickets` query scoped to the office (via its queue group) + the
     * date range on joined_at.
     *
     * @return Builder<QueueTicket>
     */
    private function ticketScope(AnalyticsFilter $filter): Builder
    {
        $query = QueueTicket::query();

        if ($filter->officeId !== null) {
            $query->whereHas('queueGroup', fn (Builder $q) => $q->where('office_id', $filter->officeId));
        }

        if ($filter->from !== null) {
            $query->where('joined_at', '>=', $filter->from);
        }

        if ($filter->to !== null) {
            $query->where('joined_at', '<=', $filter->to);
        }

        return $query;
    }

    /**
     * A driver-aware SQL expression yielding the number of seconds between two
     * timestamp columns (`$end - $start`). Postgres uses EXTRACT(EPOCH FROM diff);
     * SQLite uses julianday day-diff × 86400. Keeps the time-math out of PHP so the
     * averages/sums run in the database.
     */
    private function secondsBetween(string $start, string $end): Expression
    {
        return DB::raw($this->secondsBetweenSql($start, $end));
    }

    /**
     * The raw driver-aware seconds-between SQL string (for embedding inside a
     * SUM/AVG in a selectRaw). See {@see secondsBetween()}.
     */
    private function secondsBetweenSql(string $start, string $end): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "EXTRACT(EPOCH FROM ({$end} - {$start}))",
            'mysql', 'mariadb' => "TIMESTAMPDIFF(SECOND, {$start}, {$end})",
            default => "(julianday({$end}) - julianday({$start})) * 86400",
        };
    }
}
