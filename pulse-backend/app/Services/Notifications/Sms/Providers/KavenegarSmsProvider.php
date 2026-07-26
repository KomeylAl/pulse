<?php

namespace App\Services\Notifications\Sms\Providers;

use App\Contracts\Notifications\SmsProviderInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class KavenegarSmsProvider implements SmsProviderInterface
{
    public function send(string $phone, string $message): void
    {
        $apiKey = config('notifications.sms.kavenegar.api_key');
        $sender = config('notifications.sms.kavenegar.sender');

        if (blank($apiKey)) {
            throw new RuntimeException('Kavenegar API key is not configured.');
        }

        $response = Http::asForm()
            ->timeout(30)
            ->post("https://api.kavenegar.com/v1/{$apiKey}/sms/send.json", [
                'receptor' => $phone,
                'message' => $message,
                'sender' => $sender,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Kavenegar SMS request failed: '.$response->body());
        }

        $result = $response->json('return.status');

        if ($result !== 200) {
            throw new RuntimeException('Kavenegar SMS failed: '.$response->body());
        }
    }
}
