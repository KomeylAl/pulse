<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;

class CreateRecurringCampaignRequest extends FormRequest
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
            'schedule_times' => ['required', 'array', 'min:1'],
            'schedule_times.*' => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'target' => ['required', 'in:all,users'],
            'external_user_ids' => ['nullable', 'array', 'required_if:target,users'],
            'external_user_ids.*' => ['string', 'max:255'],
            'data' => ['nullable', 'array'],
            'priority' => ['nullable', 'in:normal,high'],
        ];
    }
}
