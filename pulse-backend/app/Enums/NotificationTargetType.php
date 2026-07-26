<?php

namespace App\Enums;

enum NotificationTargetType: string
{
    case User = 'user';
    case Users = 'users';
    case Broadcast = 'broadcast';
    case ProjectDevices = 'project_devices';
    case ExternalUsers = 'external_users';

    public function label(): string
    {
        return match ($this) {
            self::User => 'Single User',
            self::Users => 'Multiple Users',
            self::Broadcast => 'Broadcast',
            self::ProjectDevices => 'All project devices',
            self::ExternalUsers => 'External app users',
        };
    }
}
