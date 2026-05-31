<?php

namespace App\Http\Resources\Vessel;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VesselResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'                => $this->id,
            'name'                => $this->name,
            'imo_number'          => $this->imo_number,
            'mmsi'                => $this->mmsi,
            'call_sign'           => $this->call_sign,
            'carrier_scac'        => $this->carrier_scac,
            'flag'                => $this->flag,
            'vessel_type'         => $this->vessel_type,
            'voyage_number'       => $this->voyage_number,
            'pol'                 => $this->pol,
            'pod'                 => $this->pod,
            'eta'                 => $this->eta?->toIso8601String(),
            'etd'                 => $this->etd?->toIso8601String(),
            'ata'                 => $this->ata?->toIso8601String(),
            'atd'                 => $this->atd?->toIso8601String(),
            'position'            => $this->when(
                $this->current_latitude !== null,
                fn () => [
                    'lat' => (float) $this->current_latitude,
                    'lng' => (float) $this->current_longitude,
                ]
            ),
            'speed'               => $this->current_speed,
            'heading'             => $this->current_heading,
            'last_ais_update'     => $this->last_ais_update?->toIso8601String(),
            'metadata'            => $this->metadata,
            'created_at'          => $this->created_at?->toIso8601String(),
            'updated_at'          => $this->updated_at?->toIso8601String(),
        ];
    }
}
