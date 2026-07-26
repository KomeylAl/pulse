<?php

namespace App\Providers;

use App\Services\Notifications\Channels\PushChannel;
use App\Services\Notifications\Channels\SmsChannel;
use App\Services\Notifications\Fcm\FcmService;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\PulseProjectRegistry;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PulseProjectRegistry::class);

        $this->app->singleton(FcmService::class);

        $this->app->singleton(NotificationDispatcher::class, function ($app) {
            return new NotificationDispatcher([
                $app->make(SmsChannel::class),
                $app->make(PushChannel::class),
            ]);
        });
    }

    public function boot(): void
    {
        //
    }
}
