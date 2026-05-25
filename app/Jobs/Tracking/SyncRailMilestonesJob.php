<?php

namespace App\Jobs\Tracking;

use App\Domain\Container\Enums\ContainerStatus;
use App\Models\Tenant\Container;
use App\Services\Tracking\RailTrackingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncRailMilestonesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 180;

    public function __construct()
    {
        $this->onQueue('tracking');
    }

    public function handle(RailTrackingService $railService): void
    {
        Log::info('SyncRailMilestonesJob: starting rail milestone sync');

        $containers = Container::whereIn('status', [
            ContainerStatus::ON_RAIL->value,
            ContainerStatus::ARRIVED_AT_RAIL_TERMINAL->value,
        ])->get();

        $count = 0;

        foreach ($containers as $container) {
            try {
                $railService->pollRailMilestones($container);
                $count++;
            } catch (\Throwable $e) {
                Log::warning('SyncRailMilestonesJob: failed for container', [
                    'container_id' => $container->id,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        Log::info('SyncRailMilestonesJob: completed', ['containers_processed' => $count]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncRailMilestonesJob: failed', ['error' => $exception->getMessage()]);
    }
}
