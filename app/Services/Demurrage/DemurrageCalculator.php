<?php

namespace App\Services\Demurrage;

use App\Exceptions\DemurrageCalculationException;
use App\Models\Tenant\CarrierContract;
use App\Models\Tenant\Container;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DemurrageCalculator implements DemurrageCalculatorInterface
{
    // Default spot rates per tier when no contract exists (USD/day)
    private const DEFAULT_DEMURRAGE_TIERS = [
        ['days_from' => 1, 'days_to' => 4,    'rate' => 75.00],
        ['days_from' => 5, 'days_to' => 9,    'rate' => 150.00],
        ['days_from' => 10, 'days_to' => null, 'rate' => 250.00],
    ];

    private const DEFAULT_DETENTION_TIERS = [
        ['days_from' => 1, 'days_to' => 4,    'rate' => 50.00],
        ['days_from' => 5, 'days_to' => 9,    'rate' => 100.00],
        ['days_from' => 10, 'days_to' => null, 'rate' => 175.00],
    ];

    private const DEFAULT_FREE_DAYS_DEMURRAGE = 5;
    private const DEFAULT_FREE_DAYS_DETENTION = 5;
    private const ALARM_THRESHOLD_DAYS = 2; // alarm when within 2 days of LFD

    public function calculate(Container $container, ?CarrierContract $contract = null): DemurrageCalculation
    {
        if ($container->last_free_day_demurrage === null && $container->ata === null) {
            throw new DemurrageCalculationException(
                "Container {$container->id} has no arrival date or LFD for demurrage calculation."
            );
        }

        $contract ??= $this->getApplicableContract($container);

        $freeDays = $contract?->free_days_demurrage ?? self::DEFAULT_FREE_DAYS_DEMURRAGE;
        $rateTiers = $contract?->demurrage_rates ?? self::DEFAULT_DEMURRAGE_TIERS;

        $arrivalDate = $container->ata ?? now();
        $lastFreeDay = $container->last_free_day_demurrage
            ?? $arrivalDate->copy()->addDays($freeDays);

        $today = Carbon::today();
        $daysAccrued = max(0, (int) $today->diffInDays($lastFreeDay, false) * -1);

        [$totalCost, $currentDailyRate] = $this->applyTieredRates($daysAccrued, $rateTiers);

        $isAlarm = $daysAccrued > 0
            || $today->diffInDays($lastFreeDay, false) <= self::ALARM_THRESHOLD_DAYS;

        return new DemurrageCalculation(
            containerId: $container->id,
            freeDays: $freeDays,
            daysAccrued: $daysAccrued,
            dailyRate: $currentDailyRate,
            totalCost: $totalCost,
            rateTiers: $rateTiers,
            lastFreeDay: $lastFreeDay,
            isAlarm: $isAlarm,
        );
    }

    public function calculateDetention(Container $container, ?CarrierContract $contract = null): DetentionCalculation
    {
        if ($container->last_free_day_detention === null && $container->outgate_date === null) {
            throw new DemurrageCalculationException(
                "Container {$container->id} has no outgate date or detention LFD for detention calculation."
            );
        }

        $contract ??= $this->getApplicableContract($container);

        $freeDays = $contract?->free_days_detention ?? self::DEFAULT_FREE_DAYS_DETENTION;
        $rateTiers = $contract?->detention_rates ?? self::DEFAULT_DETENTION_TIERS;

        $outgateDate = $container->outgate_date ?? now();
        $lastFreeDay = $container->last_free_day_detention
            ?? $outgateDate->copy()->addDays($freeDays);

        $today = Carbon::today();
        $daysAccrued = max(0, (int) $today->diffInDays($lastFreeDay, false) * -1);

        [$totalCost, $currentDailyRate] = $this->applyTieredRates($daysAccrued, $rateTiers);

        $isAlarm = $daysAccrued > 0
            || $today->diffInDays($lastFreeDay, false) <= self::ALARM_THRESHOLD_DAYS;

        return new DetentionCalculation(
            containerId: $container->id,
            freeDays: $freeDays,
            daysAccrued: $daysAccrued,
            dailyRate: $currentDailyRate,
            totalCost: $totalCost,
            rateTiers: $rateTiers,
            lastFreeDay: $lastFreeDay,
            isAlarm: $isAlarm,
        );
    }

    public function getApplicableContract(Container $container): ?CarrierContract
    {
        if (empty($container->carrier_scac)) {
            return null;
        }

        $mbl = $container->mbl;

        return CarrierContract::query()
            ->where('carrier_scac', $container->carrier_scac)
            ->where('is_active', true)
            ->where('effective_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('expiry_date')->orWhere('expiry_date', '>=', now());
            })
            ->when($mbl?->port_of_discharge, fn($q, $port) => $q->where(function ($inner) use ($port) {
                $inner->where('port', $port)->orWhereNull('port');
            }))
            ->orderByRaw('CASE WHEN port IS NOT NULL THEN 0 ELSE 1 END')
            ->first();
    }

    public function projectCosts(Container $container, int $daysForward = 30): array
    {
        $contract = $this->getApplicableContract($container);
        $projections = [];

        for ($day = 0; $day <= $daysForward; $day++) {
            try {
                $demurrageFreeDays = $contract?->free_days_demurrage ?? self::DEFAULT_FREE_DAYS_DEMURRAGE;
                $demurrageRates    = $contract?->demurrage_rates ?? self::DEFAULT_DEMURRAGE_TIERS;
                $detentionFreeDays = $contract?->free_days_detention ?? self::DEFAULT_FREE_DAYS_DETENTION;
                $detentionRates    = $contract?->detention_rates ?? self::DEFAULT_DETENTION_TIERS;

                $arrivalDate = $container->ata ?? now();
                $lfdDemurrage = $container->last_free_day_demurrage
                    ?? $arrivalDate->copy()->addDays($demurrageFreeDays);

                $outgateDate = $container->outgate_date ?? $arrivalDate;
                $lfdDetention = $container->last_free_day_detention
                    ?? $outgateDate->copy()->addDays($detentionFreeDays);

                $projectedDate   = Carbon::today()->addDays($day);
                $demurrageDays   = max(0, (int) $projectedDate->diffInDays($lfdDemurrage, false) * -1);
                $detentionDays   = max(0, (int) $projectedDate->diffInDays($lfdDetention, false) * -1);

                [$demurrageCost]  = $this->applyTieredRates($demurrageDays, $demurrageRates);
                [$detentionCost]  = $this->applyTieredRates($detentionDays, $detentionRates);

                $projections[] = [
                    'date'                => $projectedDate->toDateString(),
                    'day_offset'          => $day,
                    'demurrage_days'      => $demurrageDays,
                    'demurrage_cost'      => $demurrageCost,
                    'detention_days'      => $detentionDays,
                    'detention_cost'      => $detentionCost,
                    'total_cost'          => $demurrageCost + $detentionCost,
                ];
            } catch (\Throwable $e) {
                Log::warning('DemurrageCalculator: projection skipped for day ' . $day, [
                    'container_id' => $container->id,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        return $projections;
    }

    /**
     * Apply tiered rate schedule to days accrued.
     *
     * @return array{float, float} [total_cost, current_daily_rate]
     */
    private function applyTieredRates(int $daysAccrued, array $tiers): array
    {
        if ($daysAccrued === 0) {
            return [0.0, 0.0];
        }

        $totalCost       = 0.0;
        $currentDailyRate = 0.0;

        foreach ($tiers as $tier) {
            $tierFrom = (int) ($tier['days_from'] ?? 1);
            $tierTo   = isset($tier['days_to']) ? (int) $tier['days_to'] : null;
            $rate     = (float) ($tier['rate'] ?? 0);

            if ($daysAccrued < $tierFrom) {
                break;
            }

            $tierMax         = $tierTo !== null ? min($daysAccrued, $tierTo) : $daysAccrued;
            $daysInTier      = $tierMax - $tierFrom + 1;
            $totalCost      += $daysInTier * $rate;
            $currentDailyRate = $rate;
        }

        return [$totalCost, $currentDailyRate];
    }
}
