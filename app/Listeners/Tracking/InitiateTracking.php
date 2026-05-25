<?php

namespace App\Listeners\Tracking;

use App\Events\Container\ContainerCreated;
use App\Services\Tracking\ContainerTrackingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class InitiateTracking implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(
        private readonly ContainerTrackingService $trackingService,
    ) {}

    public function handle(ContainerCreated $event): void
    {
        $container = $event->container;

        // Only initiate tracking if we have a reference we can track
        $mbl = $container->mbl;

        if ($mbl === null && empty($container->carrier_scac)) {
            return;
        }

        try {
            $this->trackingService->createTrackingRequest([
                'organization_id' => $container->organization_id,
                'container_id'    => $container->id,
                'mbl_id'          => $container->mbl_id,
                'booking_id'      => $container->booking_id,
                'mbl_number'      => $mbl?->mbl_number,
                'carrier_scac'    => $container->carrier_scac,
                'reference_type'  => 'container',
                'reference'       => $container->container_number,
            ]);
        } catch (\Throwable $e) {
            Log::error('InitiateTracking: failed to create tracking request', [
                'container_id' => $container->id,
                'error'        => $e->getMessage(),
            ]);
        }
    }
}
