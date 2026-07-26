<?php

namespace App\Jobs;

use App\Enums\NotificationStatus;
use App\Models\NotificationCampaign;
use App\Services\Notifications\NotificationManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessNotificationCampaignJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $campaignId,
    ) {
        $this->onQueue(config('notifications.queue'));
    }

    public function handle(NotificationManager $manager): void
    {
        $campaign = NotificationCampaign::query()->findOrFail($this->campaignId);
        $manager->processCampaign($campaign);
    }

    public function failed(Throwable $exception): void
    {
        NotificationCampaign::query()
            ->whereKey($this->campaignId)
            ->update([
                'status' => NotificationStatus::Failed,
                'error_message' => $exception->getMessage(),
            ]);
    }
}
