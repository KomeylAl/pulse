<?php

namespace App\Enums;

enum NotificationPriority: string
{
    case Normal = 'normal';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::High => 'High',
        };
    }
}
