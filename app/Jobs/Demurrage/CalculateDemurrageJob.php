<?php

namespace App\Jobs\Demurrage;

use App\Domain\Container\Enums\ContainerStatus;
use App\Models\Tenant\Container;
use App\Models\Tenant\DemurrageCharge;
use App\Notifications\DemurrageAlarmNotification;
use App\Services\Demurrage\DemurrageCalculator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CalculateDemurrageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 600;

    public function __construct()
    {
        $this->onQueue('demurrage');
    }

    public function handle(DemurrageCalculator $calculator): void
    {
        Log::info('CalculateDemurrageJob: starting demurrage calculation');

        $containers = Container::where('status', ContainerStatus::AT_OCEAN_TERMINAL)
            ->whereNotNull('ata')
            ->get();

        $count = 0;

        foreach ($containers as $container) {
            try {
                $calculation = $calculator->calculate($container);

                DemurrageCharge::updateOrCreate(
                    ['container_id' => $container->id],
                    [
                        'organization_id' => $container->organization_id,
                        'free_days'       => $calculation->freeDays,
                        'days_accrued'    => $calculation->daysAccrued,
                        'daily_rate'      => $calculation->dailyRate,
                        'estimated_cost'  => $calculation->totalCost,
                        'last_free_day'   => $calculation->lastFreeDay,
                        'alarm_active'    => $calculation->isAlarm,
                    ]
                );

                if ($calculation->isAlarm && $container->organization) {
                    $container->organization->notify(
                        new DemurrageAlarmNotification($container, $calculation)
                    );
                }

                $count++;
            } catch (\Throwable $e) {
                Log::warning('CalculateDemurrageJob: failed for container', [
                    'container_id' => $container->id,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        Log::info('CalculateDemurrageJob: completed', ['containers_processed' => $count]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('CalculateDemurrageJob: failed', ['error' => $exception->getMessage()]);
    }
}
