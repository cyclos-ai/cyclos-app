<?php

namespace App\Http\Resources\Demurrage;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DemurrageChargeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $today      = now()->startOfDay();
        $lfd        = $this->last_free_day ? \Carbon\Carbon::parse($this->last_free_day)->startOfDay() : null;
        $daysUntilLfd = $lfd ? $today->diffInDays($lfd, false) : null;

        return [
            'uuid'              => $this->uuid,
            'container_uuid'    => $this->container_uuid,
            'carrier_scac'      => $this->carrier_scac,
            'discharge_date'    => $this->discharge_date,
            'last_free_day'     => $this->last_free_day,
            'outgate_date'      => $this->outgate_date,
            'free_days'         => (int) $this->free_days,
            'daily_rate'        => (float) $this->daily_rate,
            'total_charge'      => (float) $this->total_charge,
            'currency'          => $this->currency ?? 'USD',
            'status'            => $this->status,
            'days_until_lfd'    => $daysUntilLfd,
            'is_alarm'          => $daysUntilLfd !== null && $daysUntilLfd <= 2 && $daysUntilLfd >= 0,
            'created_at'        => $this->created_at?->toIso8601String(),
            'updated_at'        => $this->updated_at?->toIso8601String(),
            'container'         => $this->whenLoaded('container', fn() => new \App\Http\Resources\Container\ContainerResource($this->container)),
        ];
    }
}
