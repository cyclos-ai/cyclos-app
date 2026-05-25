<?php

namespace App\Http\Resources\Air;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AirShipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'          => $this->uuid,
            'awb_number'    => $this->awb_number,
            'carrier_code'  => $this->carrier_code,
            'origin'        => $this->origin,
            'destination'   => $this->destination,
            'shipper'       => $this->shipper,
            'consignee'     => $this->consignee,
            'weight_kg'     => $this->weight_kg !== null ? (float) $this->weight_kg : null,
            'pieces'        => $this->pieces,
            'commodity'     => $this->commodity,
            'status'        => $this->status,
            'etd'           => $this->etd,
            'eta'           => $this->eta,
            'atd'           => $this->atd,
            'ata'           => $this->ata,
            'milestones'    => $this->whenLoaded('milestones'),
            'created_at'    => $this->created_at?->toIso8601String(),
            'updated_at'    => $this->updated_at?->toIso8601String(),
        ];
    }
}
