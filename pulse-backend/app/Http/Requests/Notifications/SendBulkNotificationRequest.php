<?php

namespace App\Http\Requests\Notifications;

use App\Enums\NotificationChannelType;
use App\Enums\NotificationPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendBulkNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['required', Rule::enum(NotificationChannelType::class)],
            'data' => ['nullable', 'array'],
            'priority' => ['nullable', Rule::enum(NotificationPriority::class)],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
