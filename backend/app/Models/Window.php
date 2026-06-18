<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WindowStatus;
use Database\Factories\WindowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Window extends Model
{
    /** @use HasFactory<WindowFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'office_id',
        'name',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WindowStatus::class,
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
     * @return BelongsToMany<QueueGroup, $this>
     */
    public function queueGroups(): BelongsToMany
    {
        return $this->belongsToMany(QueueGroup::class, 'window_queue_groups')
            ->withTimestamps();
    }

    /**
     * @return HasMany<WindowAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(WindowAssignment::class);
    }

    /**
     * @return HasMany<WindowAssignment, $this>
     */
    public function currentAssignment(): HasMany
    {
        return $this->hasMany(WindowAssignment::class)->whereNull('served_at');
    }
}
