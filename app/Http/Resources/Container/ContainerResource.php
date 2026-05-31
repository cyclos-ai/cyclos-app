<?php

namespace App\Http\Resources\Container;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContainerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'                    => $this->id,
            'container_number'        => $this->container_number,
            'carrier_scac'            => $this->carrier_scac,
            'shipping_line'           => $this->shipping_line,
            'size'                    => $this->size,
            'type'                    => $this->type,
            'weight'                  => $this->weight,
            'weight_unit'             => $this->weight_unit,
            'seal_number'             => $this->seal_number,
            'pol'                     => $this->pol,
            'pod'                     => $this->pod,
            'final_destination'       => $this->final_destination,
            'eta'                     => $this->eta?->toIso8601String(),
            'etd'                     => $this->etd?->toIso8601String(),
            'ata'                     => $this->ata?->toIso8601String(),
            'atd'                     => $this->atd?->toIso8601String(),
            'status'                  => $this->status,
            'priority'                => (bool) $this->is_priority,
            'priority_note'           => $this->priority_note,
            'last_free_day'           => $this->last_free_day_demurrage?->toIso8601String(),
            'last_free_day_detention' => $this->last_free_day_detention?->toIso8601String(),
            'outgate_date'            => $this->outgate_date?->toIso8601String(),
            'empty_return_date'       => $this->empty_return_date?->toIso8601String(),
            'mbl_uuid'                => $this->mbl_id,
            'mbl_number'              => $this->whenLoaded('mbl', fn () => $this->mbl?->mbl_number),
            'vessel_uuid'             => $this->vessel_id,
            'booking_uuid'            => $this->booking_id,
            'notes'                   => $this->notes,
            'metadata'                => $this->metadata,
            'created_at'              => $this->created_at?->toIso8601String(),
            'updated_at'              => $this->updated_at?->toIso8601String(),
            // Conditional relationships
            'mbl'                     => $this->whenLoaded('mbl', fn () => new \App\Http\Resources\MBL\MBLResource($this->mbl)),
            'vessel'                  => $this->whenLoaded('vessel', fn () => new \App\Http\Resources\Vessel\VesselResource($this->vessel)),
            'booking'                 => $this->whenLoaded('booking', fn () => new \App\Http\Resources\Booking\BookingResource($this->booking)),
        ];
    }
}
