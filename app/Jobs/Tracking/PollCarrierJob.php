<?php

namespace App\Jobs\Tracking;

use App\Models\Tenant\TrackingRequest;
use App\Notifications\TrackingRequestFailedNotification;
use App\Services\Tracking\ContainerTrackingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PollCarrierJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct(
        public readonly TrackingRequest $trackingRequest,
    ) {
        $this->onQueue('tracking');
    }

    public function handle(ContainerTrackingService $trackingService): void
    {
        $trackingService->pollCarrierUpdates($this->trackingRequest);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('PollCarrierJob: all retries exhausted', [
            'tracking_request_id' => $this->trackingRequest->id,
            'error'               => $exception->getMessage(),
        ]);

        $this->trackingRequest->update(['status' => 'failed']);

        // Notify the organization if container exists
        $container = $this->trackingRequest->container;
        if ($container?->organization) {
            $container->organization->notify(
                new TrackingRequestFailedNotification($this->trackingRequest, $exception->getMessage())
            );
        }
    }

    public function backoff(): array
    {
        return [30, 120, 300]; // 30s, 2min, 5min
    }
}
