<?php

namespace App\Http\Resources\CarrierContract;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarrierContractResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'                  => $this->uuid,
            'carrier_scac'          => $this->carrier_scac,
            'contract_type'         => $this->contract_type,
            'free_days_demurrage'   => (int) $this->free_days_demurrage,
            'free_days_detention'   => $this->free_days_detention !== null ? (int) $this->free_days_detention : null,
            'demurrage_rates'       => $this->demurrage_rates,
            'detention_rates'       => $this->detention_rates,
            'effective_date'        => $this->effective_date,
            'expiry_date'           => $this->expiry_date,
            'currency'              => $this->currency ?? 'USD',
            'is_active'             => (bool) $this->is_active,
            'notes'                 => $this->notes,
            'created_at'            => $this->created_at?->toIso8601String(),
            'updated_at'            => $this->updated_at?->toIso8601String(),
        ];
    }
}
