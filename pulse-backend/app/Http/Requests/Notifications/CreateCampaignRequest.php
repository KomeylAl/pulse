<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCampaignRequest extends FormRequest
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
        $type = $this->string('schedule_type')->value();

        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'schedule_type' => ['required', Rule::in(['once', 'daily', 'dates', 'weekly'])],
            'target' => ['required', 'in:all,users'],
            'external_user_ids' => ['nullable', 'array', 'required_if:target,users'],
            'external_user_ids.*' => ['string', 'max:255'],
            'image_url' => ['nullable', 'string', 'max:500', 'url'],
            'data' => ['nullable', 'array'],
            'priority' => ['nullable', 'in:normal,high'],

            'scheduled_at' => [
                Rule::requiredIf($type === 'once'),
                'nullable',
                'date',
            ],

            'schedule_times' => [
                Rule::requiredIf(in_array($type, ['daily', 'dates', 'weekly'], true)),
                'nullable',
                'array',
                'min:1',
            ],
            'schedule_times.*' => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],

            'starts_on' => [
                Rule::requiredIf(in_array($type, ['daily', 'weekly'], true)),
                'nullable',
                'date_format:Y-m-d',
            ],
            'ends_on' => [
                Rule::requiredIf(in_array($type, ['daily', 'weekly'], true)),
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:starts_on',
            ],

            'dates' => [
                Rule::requiredIf($type === 'dates'),
                'nullable',
                'array',
                'min:1',
            ],
            'dates.*' => ['required', 'date_format:Y-m-d'],

            'weekdays' => [
                Rule::requiredIf($type === 'weekly'),
                'nullable',
                'array',
                'min:1',
            ],
            'weekdays.*' => ['required', 'integer', 'between:1,7'],
        ];
    }
}
