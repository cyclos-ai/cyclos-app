<?php

namespace App\Http\Resources\Terminal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TerminalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'         => $this->uuid,
            'locode'       => $this->locode,
            'name'         => $this->name,
            'city'         => $this->city,
            'country_code' => $this->country_code,
            'firms_code'   => $this->firms_code,
            'terminal_code'=> $this->terminal_code,
            'latitude'     => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude'    => $this->longitude !== null ? (float) $this->longitude : null,
            'is_active'    => (bool) $this->is_active,
            'created_at'   => $this->created_at?->toIso8601String(),
            'updated_at'   => $this->updated_at?->toIso8601String(),
        ];
    }
}
