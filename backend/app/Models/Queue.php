<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QueueStatus;
use Database\Factories\QueueFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Queue extends Model
{
    /** @use HasFactory<QueueFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'office_id',
        'date',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => QueueStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Office, $this>
     */
    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    /**
     * @return HasMany<QueueTicket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(QueueTicket::class);
    }

    /**
     * @param  Builder<Queue>  $query
     */
    public function scopeForToday(Builder $query): void
    {
        $query->whereDate('date', today());
    }

    /**
     * @param  Builder<Queue>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', QueueStatus::Open);
    }
}
