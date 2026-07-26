<?php

namespace App\Http\Requests\Push;

use Illuminate\Foundation\Http\FormRequest;

class SendPushNotificationRequest extends FormRequest
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
            'external_user_id' => ['nullable', 'string', 'max:255', 'required_without:tokens'],
            'tokens' => ['nullable', 'array', 'min:1', 'required_without:external_user_id'],
            'tokens.*' => ['string', 'max:512'],
            'data' => ['nullable', 'array'],
            'priority' => ['nullable', 'in:normal,high'],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }
}
