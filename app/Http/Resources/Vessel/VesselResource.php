<?php

namespace App\Http\Resources\Vessel;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VesselResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'                => $this->uuid,
            'name'                => $this->name,
            'imo'                 => $this->imo,
            'mmsi'                => $this->mmsi,
            'carrier_scac'        => $this->carrier_scac,
            'flag'                => $this->flag,
            'vessel_type'         => $this->vessel_type,
            'current_port'        => $this->current_port,
            'destination_port'    => $this->destination_port,
            'eta'                 => $this->eta,
            'etd'                 => $this->etd,
            'ata'                 => $this->ata,
            'atd'                 => $this->atd,
            'position'            => $this->when(
                $this->current_latitude !== null,
                fn() => [
                    'lat' => (float) $this->current_latitude,
                    'lng' => (float) $this->current_longitude,
                ]
            ),
            'speed'               => $this->speed,
            'heading'             => $this->heading,
            'container_count'     => $this->whenCounted('containers'),
            'created_at'          => $this->created_at?->toIso8601String(),
            'updated_at'          => $this->updated_at?->toIso8601String(),
        ];
    }
}
