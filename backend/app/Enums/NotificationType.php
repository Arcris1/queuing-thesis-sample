<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationType: string
{
    case PositionUpdate = 'position_update';
    case EtaUpdate = 'eta_update';
    case Proceed = 'proceed';
    case ReconnectWarning = 'reconnect_warning';

    public function label(): string
    {
        return match ($this) {
            self::PositionUpdate => 'Position Update',
            self::EtaUpdate => 'ETA Update',
            self::Proceed => 'Proceed to Window',
            self::ReconnectWarning => 'Reconnect Warning',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PositionUpdate => 'blue',
            self::EtaUpdate => 'indigo',
            self::Proceed => 'green',
            self::ReconnectWarning => 'amber',
        };
    }
}
