<?php

namespace App\Services\Notifications\Fcm;

use App\Models\DeviceToken;
use App\Support\PulseProject;
use App\Support\PulseProjectRegistry;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\WebPushConfig;
use RuntimeException;

class FcmService
{
    /** @var array<string, Messaging> */
    private array $messagingClients = [];

    public function __construct(
        private readonly PulseProjectRegistry $projects,
    ) {}

    /**
     * @param  array<string, string>  $data
     */
    public function sendToToken(DeviceToken $deviceToken, string $title, string $body, array $data = []): void
    {
        $project = $this->projects->findOrFail($deviceToken->project_key);
        $messaging = $this->messagingFor($project);

        $notification = Notification::create($title, $body);

        $message = CloudMessage::new()
            ->withToken($deviceToken->token)
            ->withNotification($notification)
            ->withData($this->normalizeData($data));

        if ($deviceToken->platform->value === 'pwa') {
            $icon = is_string($data['icon'] ?? null) && $data['icon'] !== ''
                ? $data['icon']
                : $project->fcm['web_icon'];
            $image = is_string($data['image'] ?? null) && $data['image'] !== ''
                ? $data['image']
                : null;

            $webNotification = [
                'title' => $title,
                'body' => $body,
                'icon' => $icon,
            ];

            if ($image !== null) {
                $webNotification['image'] = $image;
            }

            $message = $message->withWebPushConfig(
                WebPushConfig::fromArray([
                    'notification' => $webNotification,
                    'fcm_options' => [
                        'link' => $data['url'] ?? $project->fcm['default_link'],
                    ],
                ])
            );
        }

        try {
            $messaging->send($message);
            $deviceToken->update(['last_used_at' => now()]);
        } catch (MessagingException $exception) {
            if ($this->isInvalidToken($exception)) {
                $deviceToken->update(['is_active' => false]);
            }

            throw $exception;
        }
    }

    public function messagingFor(PulseProject $project): Messaging
    {
        if (isset($this->messagingClients[$project->key])) {
            return $this->messagingClients[$project->key];
        }

        if (! $project->hasFirebaseCredentials()) {
            throw new RuntimeException(
                "Firebase messaging is not configured for project [{$project->key}]. ".
                'Open project settings in the Pulse dashboard and paste your Firebase service account JSON.'
            );
        }

        $factory = new Factory;

        if (filled($project->firebase['credentials_json'])) {
            $factory = $factory->withServiceAccount($project->firebase['credentials_json']);
        } else {
            $path = $project->firebase['credentials'];
            $resolved = $this->resolveCredentialsPath((string) $path);

            if (! is_file($resolved)) {
                throw new RuntimeException(
                    "Firebase credentials file not found for project [{$project->key}]: {$resolved}"
                );
            }

            $factory = $factory->withServiceAccount($resolved);
        }

        return $this->messagingClients[$project->key] = $factory->createMessaging();
    }

    private function resolveCredentialsPath(string $path): string
    {
        if ($path === '') {
            return $path;
        }

        if (str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function normalizeData(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            if (str_starts_with((string) $key, '_')) {
                continue;
            }

            $normalized[(string) $key] = is_scalar($value) ? (string) $value : json_encode($value);
        }

        return $normalized;
    }

    private function isInvalidToken(MessagingException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'not found')
            || str_contains($message, 'invalid')
            || str_contains($message, 'unregistered');
    }
}
