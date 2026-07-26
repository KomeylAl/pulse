<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\NotificationChannelInterface;
use App\DTOs\Notifications\NotificationPayload;
use App\Enums\NotificationChannelType;
use App\Enums\NotificationStatus;
use App\Models\NotificationLog;
use Illuminate\Support\Collection;
use RuntimeException;

class NotificationDispatcher
{
    /** @var Collection<string, NotificationChannelInterface> */
    private Collection $channels;

    /**
     * @param  iterable<NotificationChannelInterface>  $channels
     */
    public function __construct(iterable $channels)
    {
        $this->channels = collect($channels)->keyBy(fn (NotificationChannelInterface $channel) => $channel->channel());
    }

    public function dispatch(NotificationLog $log, NotificationPayload $payload): void
    {
        $channel = $this->channels->get($log->channel->value);

        if ($channel === null) {
            throw new RuntimeException("Notification channel [{$log->channel->value}] is not registered.");
        }

        if (! $channel->supports($payload)) {
            throw new RuntimeException("Channel [{$log->channel->value}] does not support this notification.");
        }

        $log->update([
            'status' => NotificationStatus::Processing,
            'attempts' => $log->attempts + 1,
        ]);

        try {
            $channel->send($log, $payload);

            $log->update([
                'status' => NotificationStatus::Sent,
                'sent_at' => now(),
                'error_message' => null,
            ]);
        } catch (\Throwable $exception) {
            $log->update([
                'status' => NotificationStatus::Failed,
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function supportsChannel(NotificationChannelType $channel, NotificationPayload $payload): bool
    {
        $handler = $this->channels->get($channel->value);

        return $handler !== null && $handler->supports($payload);
    }
}
