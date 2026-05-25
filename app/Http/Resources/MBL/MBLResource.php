<?php

namespace App\Http\Resources\MBL;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MBLResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'                  => $this->uuid,
            'mbl_number'            => $this->mbl_number,
            'carrier_scac'          => $this->carrier_scac,
            'vessel_uuid'           => $this->vessel_uuid,
            'pol'                   => $this->pol,
            'pod'                   => $this->pod,
            'eta'                   => $this->eta,
            'etd'                   => $this->etd,
            'ata'                   => $this->ata,
            'atd'                   => $this->atd,
            'status'                => $this->status,
            'is_not_tracking'       => (bool) $this->is_not_tracking,
            'not_tracking_reason'   => $this->not_tracking_reason,
            'container_count'       => $this->whenCounted('containers'),
            'shipper'               => $this->shipper,
            'consignee'             => $this->consignee,
            'notify_party'          => $this->notify_party,
            'created_at'            => $this->created_at?->toIso8601String(),
            'updated_at'            => $this->updated_at?->toIso8601String(),
            'vessel'                => $this->whenLoaded('vessel', fn() => new \App\Http\Resources\Vessel\VesselResource($this->vessel)),
            'containers'            => $this->whenLoaded('containers', fn() => \App\Http\Resources\Container\ContainerResource::collection($this->containers)),
        ];
    }
}
