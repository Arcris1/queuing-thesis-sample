<?php

declare(strict_types=1);

namespace App\Enums;

enum PresenceStatus: string
{
    case Active = 'active';
    case Away = 'away';
    case Offline = 'offline';
    case Removed = 'removed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Away => 'Away',
            self::Offline => 'Offline',
            self::Removed => 'Removed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Away => 'amber',
            self::Offline => 'orange',
            self::Removed => 'red',
        };
    }

    /**
     * Whether a ticket in this presence state is eligible for window assignment.
     */
    public function isEligible(): bool
    {
        return $this === self::Active;
    }
}
