<?php

namespace App\Jobs;

use App\DTOs\Notifications\NotificationPayload;
use App\Enums\NotificationChannelType;
use App\Enums\NotificationStatus;
use App\Models\NotificationLog;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendNotificationJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly int $notificationLogId,
    ) {
        $this->onQueue(config('notifications.queue'));
    }

    public function handle(NotificationDispatcher $dispatcher): void
    {
        $log = NotificationLog::query()->with('user')->findOrFail($this->notificationLogId);

        if (in_array($log->status, [NotificationStatus::Sent, NotificationStatus::Cancelled], true)) {
            return;
        }

        if ($log->scheduled_at !== null && $log->scheduled_at->isFuture()) {
            return;
        }

        $payload = new NotificationPayload(
            title: $log->title,
            body: $log->body,
            channels: [$log->channel],
            data: $log->data ?? [],
            priority: $log->priority,
            user: $log->user,
            phone: $log->recipient,
            projectKey: $log->project_key,
            externalUserId: $log->external_user_id,
        );

        $dispatcher->dispatch($log, $payload);
    }

    public function failed(Throwable $exception): void
    {
        NotificationLog::query()
            ->whereKey($this->notificationLogId)
            ->update([
                'status' => NotificationStatus::Failed,
                'error_message' => $exception->getMessage(),
            ]);
    }
}
