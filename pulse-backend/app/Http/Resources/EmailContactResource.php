<?php

namespace App\Http\Resources;

use App\Models\EmailContact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmailContact */
class EmailContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_key' => $this->project_key,
            'email' => $this->email,
            'name' => $this->name,
            'external_user_id' => $this->external_user_id,
            'is_active' => $this->is_active,
            'unsubscribed_at' => $this->unsubscribed_at?->toIso8601String(),
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
