<?php

namespace App\Events\Tracking;

use App\Models\Tenant\TrackingRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TrackingRequestCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly TrackingRequest $trackingRequest,
    ) {}
}
