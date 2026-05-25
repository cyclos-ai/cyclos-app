<?php

namespace App\Listeners\Tracking;

use App\Events\Tracking\TrackingRequestCreated;
use App\Jobs\Tracking\PollCarrierJob;
use Illuminate\Contracts\Queue\ShouldQueue;

class PollCarrierForContainer implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(TrackingRequestCreated $event): void
    {
        PollCarrierJob::dispatch($event->trackingRequest)
            ->delay(now()->addSeconds(5));
    }
}
