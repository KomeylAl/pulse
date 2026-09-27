<?php

namespace App\Http\Requests\Projects;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectEmailSettingsRequest extends FormRequest
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
        $project = $this->route('project');
        $hasKey = $project instanceof Project && filled($project->resend_api_key);

        return [
            'resend_api_key' => [$hasKey ? 'nullable' : 'required', 'string', 'max:255', 'regex:/^re_[A-Za-z0-9_]+$/'],
            'email_from_name' => ['nullable', 'string', 'max:120'],
            'email_from_address' => ['required', 'email', 'max:255'],
            'email_reply_to' => ['nullable', 'email', 'max:255'],
            'resend_webhook_secret' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'resend_api_key.required' => 'کلید API رزِند را وارد کنید.',
            'resend_api_key.regex' => 'کلید API باید با re_ شروع شود.',
            'email_from_address.required' => 'آدرس فرستنده را وارد کنید.',
            'email_from_address.email' => 'آدرس فرستنده معتبر نیست.',
            'email_reply_to.email' => 'آدرس Reply-To معتبر نیست.',
        ];
    }
}
