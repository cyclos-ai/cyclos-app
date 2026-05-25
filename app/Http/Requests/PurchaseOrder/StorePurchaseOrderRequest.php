<?php

namespace App\Http\Requests\PurchaseOrder;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'po_number'             => ['required', 'string', 'max:100'],
            'vendor_uuid'           => ['nullable', 'uuid'],
            'factory_uuid'          => ['nullable', 'uuid'],
            'order_date'            => ['required', 'date'],
            'required_date'         => ['nullable', 'date', 'after_or_equal:order_date'],
            'ship_date'             => ['nullable', 'date'],
            'status'                => ['nullable', 'string', 'in:draft,confirmed,in_production,shipped,received,cancelled'],
            'currency'              => ['nullable', 'string', 'max:3'],
            'total_amount'          => ['nullable', 'numeric', 'min:0'],
            'notes'                 => ['nullable', 'string'],
            'items'                 => ['nullable', 'array'],
            'items.*.sku_uuid'      => ['nullable', 'uuid'],
            'items.*.sku_code'      => ['nullable', 'string', 'max:100'],
            'items.*.description'   => ['nullable', 'string', 'max:500'],
            'items.*.quantity'      => ['required_with:items', 'integer', 'min:1'],
            'items.*.unit_price'    => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_of_measure' => ['nullable', 'string', 'max:50'],
        ];
    }
}
