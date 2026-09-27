<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Project */
class ProjectResource extends JsonResource
{
    public function __construct($resource, private readonly bool $includeSecrets = false)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'api_key' => $this->when($this->includeSecrets, $this->api_key),
            'has_firebase' => $this->hasFirebaseCredentials(),
            'has_web_config' => filled($this->firebase_web_config),
            'has_vapid_key' => filled($this->vapid_key),
            'has_email' => $this->hasEmailChannel(),
            'has_email_webhook' => filled($this->resend_webhook_secret),
            'email_from_address' => $this->email_from_address,
            'email_from_name' => $this->email_from_name,
            'email_reply_to' => $this->email_reply_to,
            'firebase_web_config' => $this->when($this->includeSecrets, $this->firebase_web_config),
            'vapid_key' => $this->when($this->includeSecrets, $this->vapid_key),
            'fcm_web_icon' => $this->fcm_web_icon,
            'fcm_default_link' => $this->fcm_default_link,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
