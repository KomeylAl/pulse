<?php

namespace App\DTOs\Notifications;

use App\Enums\NotificationChannelType;
use App\Enums\NotificationPriority;
use App\Models\User;

readonly class NotificationPayload
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<NotificationChannelType>  $channels
     */
    public function __construct(
        public string $title,
        public string $body,
        public array $channels,
        public array $data = [],
        public NotificationPriority $priority = NotificationPriority::Normal,
        public ?User $user = null,
        public ?string $phone = null,
        public string $projectKey = 'app',
        public ?string $externalUserId = null,
        public ?string $email = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'channels' => array_map(fn (NotificationChannelType $channel) => $channel->value, $this->channels),
            'data' => $this->data,
            'priority' => $this->priority->value,
            'project_key' => $this->projectKey,
            'external_user_id' => $this->externalUserId,
            'email' => $this->email,
        ];
    }
}
