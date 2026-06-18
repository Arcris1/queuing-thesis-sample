<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PresenceStatus;
use App\Services\PresenceService;
use Database\Factories\HeartbeatFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Heartbeat extends Model
{
    /** @use HasFactory<HeartbeatFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'ticket_id',
        'last_seen',
        'battery_level',
        'network_status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen' => 'datetime',
            'battery_level' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<QueueTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(QueueTicket::class, 'ticket_id');
    }

    /**
     * Derive presence from the time since last_seen against the configured
     * thresholds (plan §9 / §15). Computed, never stored. Delegates to
     * {@see PresenceService::statusFromLastSeen()} so the Active→Away→Offline→
     * Removed rule lives in exactly one place (task 016).
     *
     * @return Attribute<PresenceStatus, never>
     */
    protected function presenceStatus(): Attribute
    {
        return Attribute::get(
            fn (): PresenceStatus => app(PresenceService::class)->statusFromLastSeen($this->last_seen),
        );
    }
}
