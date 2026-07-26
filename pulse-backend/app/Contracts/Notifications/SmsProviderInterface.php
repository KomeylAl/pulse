<?php

namespace App\Contracts\Notifications;

interface SmsProviderInterface
{
    public function send(string $phone, string $message): void;
}
