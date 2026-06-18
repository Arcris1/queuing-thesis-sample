<?php

declare(strict_types=1);

namespace App\Enums;

enum WindowStatus: string
{
    case Open = 'open';
    case Idle = 'idle';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Idle => 'Idle',
            self::Closed => 'Closed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'green',
            self::Idle => 'amber',
            self::Closed => 'gray',
        };
    }
}
