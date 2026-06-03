<?php

namespace App\Http\Requests\Container;

use Illuminate\Foundation\Http\FormRequest;

class StoreContainerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'container_number'  => ['required', 'string', 'regex:/^[A-Z]{4}\d{7}$/'],
            'carrier_scac'      => ['nullable', 'string', 'max:4'],
            'mbl_uuid'          => ['nullable', 'uuid'],
            'vessel_uuid'       => ['nullable', 'uuid'],
            'booking_uuid'      => ['nullable', 'uuid'],
            'size'              => ['nullable', 'string', 'in:20,40,45,53'],
            'type'              => ['nullable', 'string', 'in:GP,HC,RF,OT,FR,TK'],
            'weight_kg'         => ['nullable', 'numeric', 'min:0'],
            'pol'               => ['nullable', 'string', 'max:10'],
            'pod'               => ['nullable', 'string', 'max:10'],
            'eta'               => ['nullable', 'date'],
            'etd'               => ['nullable', 'date'],
            'ata'               => ['nullable', 'date'],
            'atd'               => ['nullable', 'date'],
            'priority'          => ['nullable', 'string', 'in:low,normal,high,critical'],
            'status'            => ['nullable', 'string', 'max:50'],
            'is_tracking'       => ['nullable', 'boolean'],
            'notes'             => ['nullable', 'string', 'max:1000'],
            'customs_hold'      => ['nullable', 'boolean'],
            'freight_hold'      => ['nullable', 'boolean'],
            'discharge_date'    => ['nullable', 'date'],
            'last_free_day'     => ['nullable', 'date'],
            // Vessel auto-linking fields (OCR / manual entry)
            'vessel_name'       => ['nullable', 'string', 'max:255'],
            'imo'               => ['nullable', 'string', 'max:20'],
            'mmsi'              => ['nullable', 'string', 'max:20'],
            'voyage_number'     => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'container_number.regex' => 'Container number must be 4 uppercase letters followed by 7 digits (e.g., ABCD1234567).',
        ];
    }
}
