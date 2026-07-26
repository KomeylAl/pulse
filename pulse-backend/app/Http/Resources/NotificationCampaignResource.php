<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\NotificationCampaign */
class NotificationCampaignResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_key' => $this->project_key,
            'title' => $this->title,
            'body' => $this->body,
            'image_url' => $this->image_url,
            'channels' => $this->channels,
            'target_type' => $this->target_type->value,
            'target_type_label' => $this->target_type->label(),
            'target_user_ids' => $this->target_user_ids,
            'target_external_user_ids' => $this->target_external_user_ids,
            'data' => $this->data,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'priority' => $this->priority->value,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'is_recurring' => $this->is_recurring,
            'recurrence' => $this->recurrence,
            'schedule_times' => $this->schedule_times,
            'schedule_config' => $this->schedule_config,
            'processed_at' => $this->processed_at?->toIso8601String(),
            'total_recipients' => $this->total_recipients,
            'sent_count' => $this->sent_count,
            'failed_count' => $this->failed_count,
            'error_message' => $this->error_message,
            'creator' => UserResource::make($this->whenLoaded('creator')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
