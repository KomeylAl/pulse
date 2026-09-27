<?php

namespace App\Support;

use App\Models\Project;

readonly class PulseProject
{
    /**
     * @param  array{credentials: ?string, credentials_json: ?string}  $firebase
     * @param  array{web_icon: string, default_link: string}  $fcm
     * @param  array<string, mixed>|null  $webConfig
     */
    public function __construct(
        public string $key,
        public string $name,
        public ?string $apiKey,
        public array $firebase,
        public array $fcm,
        public ?int $id = null,
        public ?string $type = null,
        public ?array $webConfig = null,
        public ?string $vapidKey = null,
        public ?int $ownerId = null,
        public ?string $emailFromAddress = null,
        public bool $hasEmail = false,
    ) {}

    public static function fromModel(Project $project): self
    {
        $credentialsJson = $project->firebase_credentials;

        return new self(
            key: $project->key,
            name: $project->name,
            apiKey: $project->api_key,
            firebase: [
                'credentials' => null,
                'credentials_json' => filled($credentialsJson) ? (string) $credentialsJson : null,
            ],
            fcm: [
                'web_icon' => $project->fcm_web_icon ?: '/icons/icon-192x192.png',
                'default_link' => $project->fcm_default_link
                    ?: (string) config('notifications.fcm.default_link', 'http://localhost:3000'),
            ],
            id: $project->id,
            type: $project->type->value,
            webConfig: $project->firebase_web_config,
            vapidKey: $project->vapid_key,
            ownerId: $project->user_id,
            emailFromAddress: $project->email_from_address,
            hasEmail: $project->hasEmailChannel(),
        );
    }

    public function hasFirebaseCredentials(): bool
    {
        return filled($this->firebase['credentials_json'])
            || filled($this->firebase['credentials']);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'type' => $this->type,
            'has_firebase' => $this->hasFirebaseCredentials(),
            'has_web_config' => filled($this->webConfig),
            'has_vapid_key' => filled($this->vapidKey),
            'has_email' => $this->hasEmail,
            'email_from_address' => $this->emailFromAddress,
            'fcm' => $this->fcm,
        ];
    }
}
