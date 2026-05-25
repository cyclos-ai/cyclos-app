<?php

namespace App\Http\Requests\Demurrage;

use Illuminate\Foundation\Http\FormRequest;

class CalculateDemurrageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'discharge_date'    => ['required', 'date'],
            'outgate_date'      => ['nullable', 'date', 'after_or_equal:discharge_date'],
            'free_days'         => ['required', 'integer', 'min:0'],
            'daily_rate'        => ['required', 'numeric', 'min:0'],
            'currency'          => ['nullable', 'string', 'max:3'],
            'container_uuid'    => ['nullable', 'uuid'],
            'carrier_scac'      => ['nullable', 'string', 'max:4'],
        ];
    }
}
