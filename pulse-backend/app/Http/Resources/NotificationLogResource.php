<?php

namespace App\Http\Resources;

use App\Models\NotificationLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin NotificationLog */
class NotificationLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_key' => $this->project_key,
            'campaign_id' => $this->campaign_id,
            'user_id' => $this->user_id,
            'external_user_id' => $this->external_user_id,
            'user' => UserResource::make($this->whenLoaded('user')),
            'channel' => $this->channel->value,
            'channel_label' => $this->channel->label(),
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'priority' => $this->priority->value,
            'recipient' => $this->recipient,
            'provider_message_id' => $this->provider_message_id,
            'delivery_status' => $this->delivery_status,
            'error_message' => $this->error_message,
            'attempts' => $this->attempts,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
