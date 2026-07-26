<?php

namespace App\Contracts\Notifications;

use App\DTOs\Notifications\NotificationPayload;
use App\Models\NotificationLog;

interface NotificationChannelInterface
{
    public function channel(): string;

    public function supports(NotificationPayload $payload): bool;

    public function send(NotificationLog $log, NotificationPayload $payload): void;
}
