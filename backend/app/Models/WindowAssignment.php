<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WindowAssignmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WindowAssignment extends Model
{
    /** @use HasFactory<WindowAssignmentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'window_id',
        'ticket_id',
        'assigned_at',
        'served_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'served_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Window, $this>
     */
    public function window(): BelongsTo
    {
        return $this->belongsTo(Window::class);
    }

    /**
     * @return BelongsTo<QueueTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(QueueTicket::class, 'ticket_id');
    }

    /**
     * Open assignments — the window's current, not-yet-completed ticket.
     *
     * @param  Builder<WindowAssignment>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('served_at');
    }
}
