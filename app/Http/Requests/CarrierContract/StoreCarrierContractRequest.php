<?php

namespace App\Http\Requests\CarrierContract;

use Illuminate\Foundation\Http\FormRequest;

class StoreCarrierContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'carrier_scac'              => ['required', 'string', 'max:4'],
            'contract_type'             => ['required', 'string', 'in:spot,custom'],
            'free_days_demurrage'       => ['required', 'integer', 'min:0'],
            'free_days_detention'       => ['nullable', 'integer', 'min:0'],
            'demurrage_rates'           => ['required', 'array'],
            'demurrage_rates.*.days_from'  => ['required', 'integer', 'min:1'],
            'demurrage_rates.*.days_to'    => ['nullable', 'integer'],
            'demurrage_rates.*.daily_rate' => ['required', 'numeric', 'min:0'],
            'detention_rates'           => ['nullable', 'array'],
            'detention_rates.*.days_from'  => ['required_with:detention_rates', 'integer', 'min:1'],
            'detention_rates.*.days_to'    => ['nullable', 'integer'],
            'detention_rates.*.daily_rate' => ['required_with:detention_rates', 'numeric', 'min:0'],
            'effective_date'            => ['required', 'date'],
            'expiry_date'               => ['nullable', 'date', 'after:effective_date'],
            'currency'                  => ['nullable', 'string', 'max:3'],
            'notes'                     => ['nullable', 'string'],
            'is_active'                 => ['nullable', 'boolean'],
        ];
    }
}
