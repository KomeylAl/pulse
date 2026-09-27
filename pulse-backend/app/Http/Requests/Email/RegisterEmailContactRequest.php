<?php

namespace App\Http\Requests\Email;

use Illuminate\Foundation\Http\FormRequest;

class RegisterEmailContactRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:120'],
            'external_user_id' => ['nullable', 'string', 'max:191'],
        ];
    }
}
