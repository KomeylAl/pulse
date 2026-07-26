<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\DeviceToken */
class DeviceTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_key' => $this->project_key,
            'token' => $this->token,
            'token_preview' => strlen($this->token) > 16
                ? substr($this->token, 0, 8).'…'.substr($this->token, -6)
                : $this->token,
            'platform' => $this->platform->value,
            'platform_label' => $this->platform->label(),
            'external_user_id' => $this->external_user_id,
            'device_name' => $this->device_name,
            'device_id' => $this->device_id,
            'is_active' => $this->is_active,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
