<?php

declare(strict_types=1);

namespace App\Enums;

enum TicketStatus: string
{
    case Waiting = 'waiting';
    case Ready = 'ready';
    case Serving = 'serving';
    case Served = 'served';
    case Skipped = 'skipped';
    case Standby = 'standby';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Waiting',
            self::Ready => 'Ready',
            self::Serving => 'Serving',
            self::Served => 'Served',
            self::Skipped => 'Skipped',
            self::Standby => 'Standby',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Waiting => 'gray',
            self::Ready => 'amber',
            self::Serving => 'blue',
            self::Served => 'green',
            self::Skipped => 'red',
            self::Standby => 'orange',
        };
    }

    /**
     * Statuses that represent a ticket still in the queue (not yet finished).
     *
     * @return array<int, self>
     */
    public static function active(): array
    {
        return [self::Waiting, self::Ready, self::Serving, self::Standby];
    }
}
