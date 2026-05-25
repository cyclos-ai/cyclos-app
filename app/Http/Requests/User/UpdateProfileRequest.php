<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'          => ['sometimes', 'string', 'max:255'],
            'email'         => ['sometimes', 'email', 'max:255', 'unique:users,email,' . $this->user()?->id],
            'phone'         => ['nullable', 'string', 'max:50'],
            'timezone'      => ['nullable', 'string', 'max:50'],
            'locale'        => ['nullable', 'string', 'max:10'],
            'notifications' => ['nullable', 'array'],
            'preferences'   => ['nullable', 'array'],
        ];
    }
}
