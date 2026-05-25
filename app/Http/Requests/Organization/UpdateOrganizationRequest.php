<?php

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'          => ['sometimes', 'string', 'max:255'],
            'website'       => ['nullable', 'url', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:50'],
            'address'       => ['nullable', 'string', 'max:500'],
            'city'          => ['nullable', 'string', 'max:100'],
            'state'         => ['nullable', 'string', 'max:100'],
            'country_code'  => ['nullable', 'string', 'max:2'],
            'zip_code'      => ['nullable', 'string', 'max:20'],
            'timezone'      => ['nullable', 'string', 'max:50'],
            'locale'        => ['nullable', 'string', 'max:10'],
            'logo_url'      => ['nullable', 'url', 'max:500'],
            'settings'      => ['nullable', 'array'],
        ];
    }
}
