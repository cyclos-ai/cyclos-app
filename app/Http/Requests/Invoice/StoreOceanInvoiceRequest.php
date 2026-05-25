<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class StoreOceanInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_number'        => ['required', 'string', 'max:100'],
            'carrier_scac'          => ['nullable', 'string', 'max:4'],
            'invoice_date'          => ['required', 'date'],
            'due_date'              => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'total_amount'          => ['required', 'numeric', 'min:0'],
            'currency'              => ['nullable', 'string', 'max:3'],
            'container_uuid'        => ['nullable', 'uuid'],
            'mbl_uuid'              => ['nullable', 'uuid'],
            'status'                => ['nullable', 'string', 'in:pending,ok_to_pay,paid,disputed,voided'],
            'notes'                 => ['nullable', 'string'],
            'items'                 => ['required', 'array', 'min:1'],
            'items.*.charge_type'   => ['required', 'string', 'max:100'],
            'items.*.amount'        => ['required', 'numeric', 'min:0'],
            'items.*.description'   => ['nullable', 'string', 'max:500'],
            'items.*.quantity'      => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_price'    => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
