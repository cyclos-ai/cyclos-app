<?php

namespace App\Listeners\Container;

use App\Domain\Container\Enums\ContainerStatus;
use App\Events\Container\ContainerStatusChanged;
use App\Models\Tenant\DemurrageCharge;
use App\Notifications\DemurrageAlarmNotification;
use App\Services\Demurrage\DemurrageCalculator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class CheckDemurrageAlarm implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(
        private readonly DemurrageCalculator $calculator,
    ) {}

    public function handle(ContainerStatusChanged $event): void
    {
        $container = $event->container;

        // Demurrage clock starts when container arrives at ocean terminal
        if ($event->newStatus !== ContainerStatus::AT_OCEAN_TERMINAL) {
            return;
        }

        try {
            $calculation = $this->calculator->calculate($container);

            $charge = DemurrageCharge::updateOrCreate(
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
                $container->organization->notify(new DemurrageAlarmNotification($container, $calculation));
            }

        } catch (\Throwable $e) {
            Log::error('CheckDemurrageAlarm: calculation failed', [
                'container_id' => $container->id,
                'error'        => $e->getMessage(),
            ]);
        }
    }
}
