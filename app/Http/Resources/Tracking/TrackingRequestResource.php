<?php

namespace App\Http\Resources\Tracking;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrackingRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'             => $this->uuid,
            'reference_number' => $this->reference_number,
            'request_type'     => $this->request_type,
            'carrier_scac'     => $this->carrier_scac,
            'status'           => $this->status,
            'is_non_party'     => (bool) $this->is_non_party,
            'last_updated'     => $this->last_updated,
            'error_message'    => $this->error_message,
            'metadata'         => $this->metadata,
            'created_at'       => $this->created_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
        ];
    }
}
