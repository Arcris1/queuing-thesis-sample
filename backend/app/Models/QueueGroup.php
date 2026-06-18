<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QueueGroupStatus;
use Database\Factories\QueueGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QueueGroup extends Model
{
    /** @use HasFactory<QueueGroupFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'office_id',
        'name',
        'prefix',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QueueGroupStatus::class,
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
     * @return HasMany<Service, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /**
     * @return HasMany<QueueTicket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(QueueTicket::class);
    }

    /**
     * @return BelongsToMany<Window, $this>
     */
    public function windows(): BelongsToMany
    {
        return $this->belongsToMany(Window::class, 'window_queue_groups')
            ->withTimestamps();
    }
}
