<?php

namespace App\Services\Notifications\Sms;

use App\Contracts\Notifications\SmsProviderInterface;
use App\Services\Notifications\Sms\Providers\KavenegarSmsProvider;
use App\Services\Notifications\Sms\Providers\LogSmsProvider;

class SmsService
{
    public function send(string $phone, string $message): void
    {
        $this->provider()->send($phone, $message);
    }

    private function provider(): SmsProviderInterface
    {
        return match (config('notifications.sms.driver')) {
            'kavenegar' => app(KavenegarSmsProvider::class),
            default => app(LogSmsProvider::class),
        };
    }
}
