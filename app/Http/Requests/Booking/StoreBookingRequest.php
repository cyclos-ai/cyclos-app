<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'booking_number'    => ['required', 'string', 'max:100'],
            'carrier_scac'      => ['nullable', 'string', 'max:4'],
            'vessel_uuid'       => ['nullable', 'uuid'],
            'pol'               => ['nullable', 'string', 'max:10'],
            'pod'               => ['nullable', 'string', 'max:10'],
            'etd'               => ['nullable', 'date'],
            'eta'               => ['nullable', 'date'],
            'container_count'   => ['nullable', 'integer', 'min:1'],
            'container_size'    => ['nullable', 'string', 'in:20,40,45,53'],
            'container_type'    => ['nullable', 'string', 'in:GP,HC,RF,OT,FR,TK'],
            'commodity'         => ['nullable', 'string', 'max:255'],
            'shipper'           => ['nullable', 'string', 'max:255'],
            'consignee'         => ['nullable', 'string', 'max:255'],
            'status'            => ['nullable', 'string', 'in:pending,confirmed,cancelled'],
            'notes'             => ['nullable', 'string'],
        ];
    }
}
