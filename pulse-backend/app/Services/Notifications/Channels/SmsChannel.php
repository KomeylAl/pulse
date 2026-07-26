<?php

namespace App\Services\Notifications\Channels;

use App\Contracts\Notifications\NotificationChannelInterface;
use App\DTOs\Notifications\NotificationPayload;
use App\Models\NotificationLog;
use App\Services\Notifications\Sms\SmsService;
use RuntimeException;

class SmsChannel implements NotificationChannelInterface
{
    public function __construct(
        private readonly SmsService $smsService,
    ) {}

    public function channel(): string
    {
        return 'sms';
    }

    public function supports(NotificationPayload $payload): bool
    {
        return $payload->phone !== null || $payload->user?->phone !== null;
    }

    public function send(NotificationLog $log, NotificationPayload $payload): void
    {
        $phone = $payload->phone ?? $payload->user?->phone;

        if (blank($phone)) {
            throw new RuntimeException('No phone number available for SMS notification.');
        }

        $message = trim($payload->title."\n".$payload->body);

        $this->smsService->send($phone, $message);

        $log->update(['recipient' => $phone]);
    }
}
