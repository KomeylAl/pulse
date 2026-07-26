<?php

namespace App\Enums;

enum NotificationChannelType: string
{
    case Sms = 'sms';
    case Push = 'push';

    public function label(): string
    {
        return match ($this) {
            self::Sms => 'SMS',
            self::Push => 'Push',
        };
    }
}
