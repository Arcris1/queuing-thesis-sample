<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TicketStatus;
use Database\Factories\QueueTicketFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class QueueTicket extends Model
{
    /** @use HasFactory<QueueTicketFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'queue_id',
        'queue_group_id',
        'service_id',
        'user_id',
        'ticket_number',
        'status',
        'priority',
        'joined_at',
        'called_at',
        'served_at',
        'grace_until',
        'grace_offered_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'priority' => 'integer',
            'joined_at' => 'datetime',
            'called_at' => 'datetime',
            'served_at' => 'datetime',
            'grace_until' => 'datetime',
            'grace_offered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Queue, $this>
     */
    public function queue(): BelongsTo
    {
        return $this->belongsTo(Queue::class);
    }

    /**
     * @return BelongsTo<QueueGroup, $this>
     */
    public function queueGroup(): BelongsTo
    {
        return $this->belongsTo(QueueGroup::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<WindowAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(WindowAssignment::class, 'ticket_id');
    }

    /**
     * @return HasMany<Heartbeat, $this>
     */
    public function heartbeats(): HasMany
    {
        return $this->hasMany(Heartbeat::class, 'ticket_id');
    }

    /**
     * @return HasMany<LocationLog, $this>
     */
    public function locationLogs(): HasMany
    {
        return $this->hasMany(LocationLog::class, 'ticket_id');
    }

    /**
     * The most recent location sample for this ticket, used by the routing
     * engine's geofence-eligibility check (task 013 plugs in here).
     *
     * @return HasOne<LocationLog, $this>
     */
    public function latestLocationLog(): HasOne
    {
        return $this->hasOne(LocationLog::class, 'ticket_id')->latestOfMany('recorded_at');
    }

    /**
     * The most recent heartbeat for this ticket, used by the routing engine's
     * presence-eligibility check (task 016 plugs in here).
     *
     * @return HasOne<Heartbeat, $this>
     */
    public function latestHeartbeat(): HasOne
    {
        return $this->hasOne(Heartbeat::class, 'ticket_id')->latestOfMany('last_seen');
    }

    /**
     * @param  Builder<QueueTicket>  $query
     */
    public function scopeWaiting(Builder $query): void
    {
        $query->where('status', TicketStatus::Waiting);
    }

    /**
     * @param  Builder<QueueTicket>  $query
     */
    public function scopeForToday(Builder $query): void
    {
        $query->whereDate('joined_at', today());
    }

    /**
     * Oldest eligible assignable tickets across the given queue groups, ordered for
     * the routing engine (plan §5.3 / §5.5):
     *
     *   1. higher priority first, then
     *   2. on-site (checked-in) tickets first — Ready outranks Waiting at equal
     *      priority because a Ready student has proven physical arrival (§11), then
     *   3. FIFO by joined_at.
     *
     * Both Waiting and Ready are candidates: a Ready (checked-in) ticket SHOULD be
     * assignable and is in fact preferred (task 017 — Ready-vs-routing resolution).
     * Standby/Serving/Served/Skipped are excluded — Standby re-enters only by
     * becoming Waiting again on the student's return (task 017).
     *
     * @param  Builder<QueueTicket>  $query
     * @param  array<int, int>  $queueGroupIds
     */
    public function scopeWaitingEligibleOldest(Builder $query, array $queueGroupIds): void
    {
        $query
            ->whereIn('queue_group_id', $queueGroupIds)
            ->whereIn('status', [TicketStatus::Ready, TicketStatus::Waiting])
            ->orderByDesc('priority')
            // Ready ('ready') sorts before Waiting ('waiting') ascending, which
            // happens to encode the desired "checked-in first" rank; make it
            // explicit so the intent survives any future status renames.
            ->orderByRaw('CASE status WHEN ? THEN 0 ELSE 1 END', [TicketStatus::Ready->value])
            ->orderBy('joined_at');
    }
}
