<?php

namespace App\Http\Requests\Email;

use App\Enums\NotificationPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendEmailNotificationRequest extends FormRequest
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
            'to' => ['required_without:external_user_id', 'array', 'min:1', 'max:50'],
            'to.*' => ['required', 'email', 'max:255'],
            'external_user_id' => ['required_without:to', 'nullable', 'string', 'max:191'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'html' => ['nullable', 'string', 'max:100000'],
            'reply_to' => ['nullable', 'email', 'max:255'],
            'data' => ['nullable', 'array'],
            'priority' => ['nullable', Rule::enum(NotificationPriority::class)],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
