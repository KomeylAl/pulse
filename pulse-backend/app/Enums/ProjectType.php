<?php

namespace App\Enums;

enum ProjectType: string
{
    case Pwa = 'pwa';
    case Android = 'android';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Pwa => 'PWA / Web',
            self::Android => 'Android',
            self::Both => 'PWA + Android',
        };
    }
}
