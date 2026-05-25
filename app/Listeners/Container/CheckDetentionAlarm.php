<?php

namespace App\Listeners\Container;

use App\Domain\Container\Enums\ContainerStatus;
use App\Events\Container\ContainerStatusChanged;
use App\Models\Tenant\DetentionCharge;
use App\Notifications\DetentionAlarmNotification;
use App\Services\Demurrage\DemurrageCalculator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class CheckDetentionAlarm implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(
        private readonly DemurrageCalculator $calculator,
    ) {}

    public function handle(ContainerStatusChanged $event): void
    {
        $container = $event->container;

        // Detention clock starts when container gates out of the terminal
        if ($event->newStatus !== ContainerStatus::OUT_FOR_DELIVERY) {
            return;
        }

        try {
            $calculation = $this->calculator->calculateDetention($container);

            DetentionCharge::updateOrCreate(
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
                $container->organization->notify(new DetentionAlarmNotification($container, $calculation));
            }

        } catch (\Throwable $e) {
            Log::error('CheckDetentionAlarm: calculation failed', [
                'container_id' => $container->id,
                'error'        => $e->getMessage(),
            ]);
        }
    }
}
