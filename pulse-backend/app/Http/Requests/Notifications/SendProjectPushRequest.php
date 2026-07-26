<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;

class SendProjectPushRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'target' => ['required', 'in:all,users,devices'],
            'external_user_ids' => ['nullable', 'array', 'required_if:target,users'],
            'external_user_ids.*' => ['string', 'max:255'],
            'device_ids' => ['nullable', 'array', 'required_if:target,devices'],
            'device_ids.*' => ['integer'],
            'data' => ['nullable', 'array'],
            'priority' => ['nullable', 'in:normal,high'],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }
}
