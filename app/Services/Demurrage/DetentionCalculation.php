<?php

namespace App\Services\Demurrage;

use Carbon\Carbon;

class DetentionCalculation
{
    public function __construct(
        public readonly string $containerId,
        public readonly int $freeDays,
        public readonly int $daysAccrued,
        public readonly float $dailyRate,
        public readonly float $totalCost,
        public readonly array $rateTiers,
        public readonly ?Carbon $lastFreeDay,
        public readonly bool $isAlarm,
    ) {}

    public function toArray(): array
    {
        return [
            'container_id'  => $this->containerId,
            'free_days'     => $this->freeDays,
            'days_accrued'  => $this->daysAccrued,
            'daily_rate'    => $this->dailyRate,
            'total_cost'    => $this->totalCost,
            'rate_tiers'    => $this->rateTiers,
            'last_free_day' => $this->lastFreeDay?->toDateTimeString(),
            'is_alarm'      => $this->isAlarm,
        ];
    }
}
