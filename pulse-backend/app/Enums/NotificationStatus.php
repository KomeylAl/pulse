<?php

namespace App\Enums;

enum NotificationStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Sent = 'sent';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Scheduled = 'scheduled';
    case Active = 'active';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Sent => 'Sent',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
            self::Scheduled => 'Scheduled',
            self::Active => 'Active (recurring)',
        };
    }
}
