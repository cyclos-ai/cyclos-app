<?php

namespace App\Events\Vessel;

use App\Models\Tenant\Vessel;
use Carbon\Carbon;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VesselETAUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Vessel $vessel,
        public readonly ?Carbon $previousETA,
        public readonly ?Carbon $newETA,
    ) {}
}
