<?php

declare(strict_types=1);

namespace App\Enums;

enum Role: string
{
    case Student = 'student';
    case Staff = 'staff';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Student => 'Student',
            self::Staff => 'Staff',
            self::Admin => 'Administrator',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Student => 'blue',
            self::Staff => 'green',
            self::Admin => 'purple',
        };
    }
}
