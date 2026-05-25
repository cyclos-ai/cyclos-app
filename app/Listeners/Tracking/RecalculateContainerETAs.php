<?php

namespace App\Listeners\Tracking;

use App\Events\Vessel\VesselETAUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class RecalculateContainerETAs implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(VesselETAUpdated $event): void
    {
        $vessel = $event->vessel;
        $newETA = $event->newETA;

        if ($newETA === null) {
            return;
        }

        $updated = $vessel->containers()
            ->whereNull('ata')
            ->update(['eta' => $newETA]);

        Log::info('RecalculateContainerETAs: updated container ETAs', [
            'vessel_id'   => $vessel->id,
            'new_eta'     => $newETA->toDateTimeString(),
            'containers_updated' => $updated,
        ]);
    }
}
