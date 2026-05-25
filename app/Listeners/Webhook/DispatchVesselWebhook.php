<?php

namespace App\Listeners\Webhook;

use App\Events\Vessel\VesselETAUpdated;
use App\Jobs\Webhook\DispatchWebhookJob;
use Illuminate\Contracts\Queue\ShouldQueue;

class DispatchVesselWebhook implements ShouldQueue
{
    public string $queue = 'webhooks';

    public function handle(VesselETAUpdated $event): void
    {
        $payload = [
            'event'      => 'vessel.eta.updated',
            'occurred_at'=> now()->toIso8601String(),
            'data'       => [
                'vessel_id'      => $event->vessel->id,
                'vessel_name'    => $event->vessel->name,
                'previous_eta'   => $event->previousETA?->toIso8601String(),
                'new_eta'        => $event->newETA?->toIso8601String(),
            ],
        ];

        DispatchWebhookJob::dispatch('vessel.eta.updated', $payload);
    }
}
