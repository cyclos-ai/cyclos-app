<?php

namespace App\Http\Requests\Tracking;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrackingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reference_number' => ['required', 'string', 'max:100'],
            'request_type'     => ['required', 'string', 'in:MBL,CONTAINER,BOOKING,AWB'],
            'carrier_scac'     => ['nullable', 'string', 'max:4'],
            'is_non_party'     => ['nullable', 'boolean'],
            'metadata'         => ['nullable', 'array'],
        ];
    }
}
