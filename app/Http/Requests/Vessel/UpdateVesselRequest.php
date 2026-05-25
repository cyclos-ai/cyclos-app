<?php

namespace App\Http\Requests\Vessel;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVesselRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                  => ['sometimes', 'string', 'max:255'],
            'imo'                   => ['nullable', 'string', 'max:20'],
            'mmsi'                  => ['nullable', 'string', 'max:20'],
            'carrier_scac'          => ['nullable', 'string', 'max:4'],
            'flag'                  => ['nullable', 'string', 'max:3'],
            'current_port'          => ['nullable', 'string', 'max:10'],
            'destination_port'      => ['nullable', 'string', 'max:10'],
            'eta'                   => ['nullable', 'date'],
            'etd'                   => ['nullable', 'date'],
            'current_latitude'      => ['nullable', 'numeric', 'between:-90,90'],
            'current_longitude'     => ['nullable', 'numeric', 'between:-180,180'],
            'speed'                 => ['nullable', 'numeric', 'min:0'],
            'heading'               => ['nullable', 'numeric', 'between:0,360'],
            'notes'                 => ['nullable', 'string'],
        ];
    }
}
