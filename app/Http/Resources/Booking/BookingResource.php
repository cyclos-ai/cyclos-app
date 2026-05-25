<?php

namespace App\Http\Resources\Booking;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'             => $this->uuid,
            'booking_number'   => $this->booking_number,
            'carrier_scac'     => $this->carrier_scac,
            'vessel_uuid'      => $this->vessel_uuid,
            'pol'              => $this->pol,
            'pod'              => $this->pod,
            'etd'              => $this->etd,
            'eta'              => $this->eta,
            'container_count'  => $this->container_count,
            'container_size'   => $this->container_size,
            'container_type'   => $this->container_type,
            'commodity'        => $this->commodity,
            'shipper'          => $this->shipper,
            'consignee'        => $this->consignee,
            'status'           => $this->status,
            'notes'            => $this->notes,
            'created_at'       => $this->created_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
        ];
    }
}
