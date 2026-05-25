<?php

namespace App\Http\Resources\Container;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContainerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'               => $this->uuid,
            'container_number'   => $this->container_number,
            'carrier_scac'       => $this->carrier_scac,
            'size'               => $this->size,
            'type'               => $this->type,
            'weight_kg'          => $this->weight_kg,
            'pol'                => $this->pol,
            'pod'                => $this->pod,
            'eta'                => $this->eta,
            'etd'                => $this->etd,
            'ata'                => $this->ata,
            'atd'                => $this->atd,
            'status'             => $this->status,
            'priority'           => $this->priority,
            'is_tracking'        => (bool) $this->is_tracking,
            'customs_hold'       => (bool) $this->customs_hold,
            'freight_hold'       => (bool) $this->freight_hold,
            'discharge_date'     => $this->discharge_date,
            'last_free_day'      => $this->last_free_day,
            'outgate_date'       => $this->outgate_date,
            'empty_return_date'  => $this->empty_return_date,
            'mbl_uuid'           => $this->mbl_uuid,
            'vessel_uuid'        => $this->vessel_uuid,
            'booking_uuid'       => $this->booking_uuid,
            'notes'              => $this->notes,
            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),
            // Conditional relationships
            'mbl'                => $this->whenLoaded('mbl', fn() => new \App\Http\Resources\MBL\MBLResource($this->mbl)),
            'vessel'             => $this->whenLoaded('vessel', fn() => new \App\Http\Resources\Vessel\VesselResource($this->vessel)),
            'booking'            => $this->whenLoaded('booking', fn() => new \App\Http\Resources\Booking\BookingResource($this->booking)),
        ];
    }
}
