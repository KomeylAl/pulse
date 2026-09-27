<?php

namespace App\Services\Notifications\Channels;

use App\Contracts\Notifications\NotificationChannelInterface;
use App\DTOs\Notifications\NotificationPayload;
use App\Models\EmailContact;
use App\Models\NotificationLog;
use App\Models\Project;
use App\Services\Notifications\Email\ResendClient;
use RuntimeException;

class EmailChannel implements NotificationChannelInterface
{
    public function __construct(
        private readonly ResendClient $resend,
    ) {}

    public function channel(): string
    {
        return 'email';
    }

    public function supports(NotificationPayload $payload): bool
    {
        $email = $this->resolveEmail($payload);

        if ($email === null || $this->isSuppressed($payload->projectKey, $email)) {
            return false;
        }

        return Project::query()
            ->where('key', $payload->projectKey)
            ->whereNotNull('resend_api_key')
            ->whereNotNull('email_from_address')
            ->exists();
    }

    public function send(NotificationLog $log, NotificationPayload $payload): void
    {
        $project = Project::query()->where('key', $payload->projectKey)->first();

        if ($project === null || ! $project->hasEmailChannel()) {
            throw new RuntimeException('Email channel is not configured for this project.');
        }

        $email = $this->resolveEmail($payload);

        if ($email === null) {
            throw new RuntimeException('No email address available for this notification.');
        }

        if ($this->isSuppressed($project->key, $email)) {
            throw new RuntimeException('Recipient is unsubscribed or suppressed.');
        }

        $replyTo = is_string($payload->data['reply_to'] ?? null) && $payload->data['reply_to'] !== ''
            ? $payload->data['reply_to']
            : $project->email_reply_to;
        $html = is_string($payload->data['html'] ?? null) ? $payload->data['html'] : null;

        $messageId = $this->resend->send(
            apiKey: (string) $project->resend_api_key,
            from: $this->resend->formatFrom($project->email_from_name, (string) $project->email_from_address),
            to: [$email],
            subject: $payload->title,
            html: $this->resend->renderHtml($payload->title, $payload->body, $html),
            text: $this->resend->plainText($payload->title, $payload->body),
            replyTo: $replyTo,
            tags: $this->tags($project->key, $log->id),
            idempotencyKey: 'pulse-email-'.$log->id,
        );

        $log->update([
            'recipient' => $email,
            'provider_message_id' => $messageId,
            'delivery_status' => 'sent',
        ]);

        EmailContact::query()
            ->where('project_key', $project->key)
            ->where('email', $email)
            ->update(['last_used_at' => now()]);
    }

    private function resolveEmail(NotificationPayload $payload): ?string
    {
        $email = $payload->email ?? $payload->user?->email;

        if (! is_string($email) || trim($email) === '') {
            if ($payload->externalUserId === null) {
                return null;
            }

            $email = EmailContact::query()
                ->where('project_key', $payload->projectKey)
                ->where('external_user_id', $payload->externalUserId)
                ->where('is_active', true)
                ->orderByDesc('last_used_at')
                ->orderByDesc('id')
                ->value('email');
        }

        if (! is_string($email) || trim($email) === '') {
            return null;
        }

        return strtolower(trim($email));
    }

    private function isSuppressed(string $projectKey, string $email): bool
    {
        return EmailContact::query()
            ->where('project_key', $projectKey)
            ->where('email', $email)
            ->where('is_active', false)
            ->exists();
    }

    /**
     * @return list<array{name: string, value: string}>
     */
    private function tags(string $projectKey, int $logId): array
    {
        $tags = [
            ['name' => 'log_id', 'value' => (string) $logId],
        ];

        if (preg_match('/^[A-Za-z0-9_-]+$/', $projectKey) === 1) {
            array_unshift($tags, ['name' => 'project_key', 'value' => $projectKey]);
        }

        return $tags;
    }
}
