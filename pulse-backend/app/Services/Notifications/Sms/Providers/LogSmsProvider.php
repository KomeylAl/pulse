<?php

namespace App\Services\Notifications\Sms\Providers;

use App\Contracts\Notifications\SmsProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LogSmsProvider implements SmsProviderInterface
{
    public function send(string $phone, string $message): void
    {
        Log::info('SMS sent (log driver)', [
            'phone' => $phone,
            'message' => $message,
        ]);
    }
}
