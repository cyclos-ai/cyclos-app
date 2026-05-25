<?php

namespace App\Events\Vessel;

use App\Models\Tenant\Vessel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VesselPositionUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Vessel $vessel,
        public readonly float $latitude,
        public readonly float $longitude,
    ) {}
}
