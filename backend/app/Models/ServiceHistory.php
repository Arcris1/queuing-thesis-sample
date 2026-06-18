<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ServiceHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceHistory extends Model
{
    /** @use HasFactory<ServiceHistoryFactory> */
    use HasFactory;

    protected $table = 'service_history';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'office_id',
        'queue_group_id',
        'service_id',
        'window_id',
        'served_at',
        'duration_minutes',
        'day_of_week',
        'hour_of_day',
        'active_windows',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'served_at' => 'datetime',
            'duration_minutes' => 'decimal:2',
            'day_of_week' => 'integer',
            'hour_of_day' => 'integer',
            'active_windows' => 'integer',
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
     * @return BelongsTo<Window, $this>
     */
    public function window(): BelongsTo
    {
        return $this->belongsTo(Window::class);
    }
}
