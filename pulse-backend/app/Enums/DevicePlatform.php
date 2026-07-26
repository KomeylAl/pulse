<?php

namespace App\Enums;

enum DevicePlatform: string
{
    case Android = 'android';
    case Pwa = 'pwa';

    public function label(): string
    {
        return match ($this) {
            self::Android => 'Android',
            self::Pwa => 'PWA',
        };
    }
}
