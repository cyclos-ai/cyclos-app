<?php

namespace App\Jobs\Tracking;

use App\Models\Tenant\Vessel;
use App\Services\Tracking\VesselTrackingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RecalculateETAsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 300;

    public function __construct()
    {
        $this->onQueue('tracking');
    }

    public function handle(VesselTrackingService $vesselService): void
    {
        Log::info('RecalculateETAsJob: starting ETA recalculation');

        $vessels = Vessel::whereNull('ata')
            ->whereNotNull('current_latitude')
            ->whereNotNull('current_longitude')
            ->get();

        $count = 0;

        foreach ($vessels as $vessel) {
            try {
                $vesselService->recalculateETA($vessel);
                $count++;
            } catch (\Throwable $e) {
                Log::warning('RecalculateETAsJob: failed for vessel', [
                    'vessel_id' => $vessel->id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        Log::info('RecalculateETAsJob: completed', ['vessels_processed' => $count]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('RecalculateETAsJob: failed', ['error' => $exception->getMessage()]);
    }
}
