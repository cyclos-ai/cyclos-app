<?php

namespace App\Http\Resources\MBL;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MBLResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'                  => $this->id,
            'mbl_number'            => $this->mbl_number,
            'carrier_scac'          => $this->carrier_scac,
            'vessel_uuid'           => $this->vessel_id,
            'pol'                   => $this->pol,
            'pod'                   => $this->pod,
            'eta'                   => $this->eta?->toIso8601String(),
            'etd'                   => $this->etd?->toIso8601String(),
            'ata'                   => $this->ata?->toIso8601String(),
            'atd'                   => $this->atd?->toIso8601String(),
            'status'                => $this->status,
            'container_count'       => $this->container_count,
            'shipper_name'          => $this->shipper_name,
            'consignee_name'        => $this->consignee_name,
            'notify_party'          => $this->notify_party,
            'metadata'              => $this->metadata,
            'created_at'            => $this->created_at?->toIso8601String(),
            'updated_at'            => $this->updated_at?->toIso8601String(),
            'vessel'                => $this->whenLoaded('vessel', fn () => new \App\Http\Resources\Vessel\VesselResource($this->vessel)),
            'containers'            => $this->whenLoaded('containers', fn () => \App\Http\Resources\Container\ContainerResource::collection($this->containers)),
        ];
    }
}
