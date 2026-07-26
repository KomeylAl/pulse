<?php

namespace App\Jobs;

use App\Enums\NotificationStatus;
use App\Models\NotificationCampaign;
use App\Models\NotificationLog;
use App\Services\Notifications\NotificationManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessScheduledNotificationsJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue(config('notifications.queue'));
    }

    public function handle(NotificationManager $manager): void
    {
        $now = now();

        $dueCampaigns = NotificationCampaign::query()
            ->where('status', NotificationStatus::Scheduled)
            ->where('is_recurring', false)
            ->where('scheduled_at', '<=', $now)
            ->get();

        foreach ($dueCampaigns as $campaign) {
            $campaign->update(['status' => NotificationStatus::Pending]);
            ProcessNotificationCampaignJob::dispatch($campaign->id);
        }

        $dueLogs = NotificationLog::query()
            ->where('status', NotificationStatus::Scheduled)
            ->where('scheduled_at', '<=', $now)
            ->pluck('id');

        foreach ($dueLogs as $logId) {
            NotificationLog::query()
                ->whereKey($logId)
                ->update(['status' => NotificationStatus::Pending]);

            SendNotificationJob::dispatch($logId);
        }

        $nowSlot = $now->format('H:i');

        $recurring = NotificationCampaign::query()
            ->where('is_recurring', true)
            ->where('status', NotificationStatus::Active)
            ->whereIn('recurrence', ['daily', 'dates', 'weekly'])
            ->get();

        foreach ($recurring as $campaign) {
            if ($manager->isRecurringCampaignExpired($campaign, $now)) {
                $campaign->update([
                    'status' => NotificationStatus::Sent,
                    'processed_at' => $now,
                ]);

                continue;
            }

            if (! $manager->shouldFireRecurringSlot($campaign, $now, $nowSlot)) {
                continue;
            }

            $manager->runRecurringSlot($campaign, $nowSlot);
        }
    }
}
