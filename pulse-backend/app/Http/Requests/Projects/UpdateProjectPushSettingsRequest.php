<?php

namespace App\Http\Requests\Projects;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectPushSettingsRequest extends FormRequest
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
            'firebase_credentials' => ['nullable', 'string'],
            'firebase_web_config' => ['nullable', 'array'],
            'firebase_web_config.apiKey' => ['nullable', 'string', 'max:255'],
            'firebase_web_config.authDomain' => ['nullable', 'string', 'max:255'],
            'firebase_web_config.projectId' => ['nullable', 'string', 'max:255'],
            'firebase_web_config.storageBucket' => ['nullable', 'string', 'max:255'],
            'firebase_web_config.messagingSenderId' => ['nullable', 'string', 'max:255'],
            'firebase_web_config.appId' => ['nullable', 'string', 'max:255'],
            'vapid_key' => ['nullable', 'string', 'max:500'],
            'fcm_web_icon' => ['nullable', 'string', 'max:500'],
            'fcm_default_link' => ['nullable', 'url', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $credentials = $this->input('firebase_credentials');

            if (! is_string($credentials) || trim($credentials) === '') {
                return;
            }

            $decoded = json_decode($credentials, true);

            if (! is_array($decoded) || ($decoded['type'] ?? null) !== 'service_account') {
                $validator->errors()->add(
                    'firebase_credentials',
                    'Firebase credentials must be a valid service account JSON.'
                );
            }
        });
    }
}
