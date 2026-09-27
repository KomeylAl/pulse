<?php

namespace App\Services\Notifications\Email;

use App\Enums\NotificationChannelType;
use App\Enums\NotificationStatus;
use App\Models\EmailContact;
use App\Models\NotificationLog;
use App\Models\Project;

class ResendWebhookHandler
{
    /**
     * @param  array<string, mixed>  $event
     */
    public function handle(Project $project, array $event): bool
    {
        $type = $event['type'] ?? null;
        $data = $event['data'] ?? null;

        if (! is_string($type) || ! is_array($data)) {
            return false;
        }

        $log = $this->findLog($project, $data);

        if ($log === null) {
            return false;
        }

        $this->apply($log, $type);

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function findLog(Project $project, array $data): ?NotificationLog
    {
        $emailId = $data['email_id'] ?? null;

        if (is_string($emailId) && $emailId !== '') {
            $log = NotificationLog::query()
                ->where('project_key', $project->key)
                ->where('provider_message_id', $emailId)
                ->first();

            if ($log !== null) {
                return $log;
            }
        }

        $logId = $this->tagValue($data, 'log_id');

        if ($logId === null || ! ctype_digit($logId)) {
            return null;
        }

        return NotificationLog::query()
            ->where('project_key', $project->key)
            ->whereKey((int) $logId)
            ->where('channel', NotificationChannelType::Email)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function tagValue(array $data, string $name): ?string
    {
        $tags = $data['tags'] ?? null;

        if (! is_array($tags)) {
            return null;
        }

        if (array_is_list($tags)) {
            foreach ($tags as $tag) {
                if (is_array($tag) && ($tag['name'] ?? null) === $name && isset($tag['value'])) {
                    return (string) $tag['value'];
                }
            }

            return null;
        }

        return isset($tags[$name]) ? (string) $tags[$name] : null;
    }

    private function apply(NotificationLog $log, string $event): void
    {
        $failures = [
            'email.bounced' => 'bounced',
            'email.complained' => 'complained',
            'email.failed' => 'failed',
            'email.suppressed' => 'suppressed',
        ];

        $progress = [
            'email.scheduled' => 'scheduled',
            'email.sent' => 'sent',
            'email.delivery_delayed' => 'delayed',
            'email.delivered' => 'delivered',
            'email.opened' => 'opened',
            'email.clicked' => 'clicked',
        ];

        $rank = [
            'scheduled' => 1,
            'sent' => 2,
            'delayed' => 3,
            'delivered' => 4,
            'opened' => 5,
            'clicked' => 6,
        ];

        if (isset($failures[$event])) {
            $log->status = NotificationStatus::Failed;
            $log->delivery_status = $failures[$event];
            $log->error_message = 'Resend reported '.$failures[$event].'.';
            $this->suppressRecipient($log);
        } elseif (isset($progress[$event]) && $log->status !== NotificationStatus::Failed) {
            $next = $progress[$event];
            $currentRank = $rank[$log->delivery_status] ?? 0;

            if (($rank[$next] ?? 0) >= $currentRank) {
                $log->delivery_status = $next;
            }
        }

        $data = $log->data ?? [];
        $events = is_array($data['email_events'] ?? null) ? $data['email_events'] : [];
        $events[] = [
            'type' => $event,
            'at' => now()->toIso8601String(),
        ];
        $data['email_events'] = array_slice($events, -20);
        $log->data = $data;
        $log->save();
    }

    private function suppressRecipient(NotificationLog $log): void
    {
        if (blank($log->recipient)) {
            return;
        }

        EmailContact::query()
            ->where('project_key', $log->project_key)
            ->where('email', strtolower($log->recipient))
            ->update([
                'is_active' => false,
                'unsubscribed_at' => now(),
            ]);
    }
}
