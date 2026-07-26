<?php

namespace App\Http\Requests\Projects;

use App\Enums\ProjectType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::enum(ProjectType::class)],
            'fcm_web_icon' => ['sometimes', 'nullable', 'string', 'max:500'],
            'fcm_default_link' => ['sometimes', 'nullable', 'url', 'max:500'],
        ];
    }
}
