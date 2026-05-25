<?php

namespace App\Jobs\Tracking;

use App\Events\Vessel\VesselPositionUpdated;
use App\Services\Tracking\VesselTrackingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PollAISPositionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 120;

    public function __construct()
    {
        $this->onQueue('tracking');
    }

    public function handle(VesselTrackingService $vesselService): void
    {
        Log::info('PollAISPositionsJob: starting AIS position poll');

        $vesselService->pollAISPositions();

        Log::info('PollAISPositionsJob: completed');
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('PollAISPositionsJob: failed', ['error' => $exception->getMessage()]);
    }
}
