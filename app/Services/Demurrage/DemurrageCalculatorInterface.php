<?php

namespace App\Services\Demurrage;

use App\Models\Tenant\CarrierContract;
use App\Models\Tenant\Container;

interface DemurrageCalculatorInterface
{
    public function calculate(Container $container, ?CarrierContract $contract = null): DemurrageCalculation;

    public function calculateDetention(Container $container, ?CarrierContract $contract = null): DetentionCalculation;

    public function getApplicableContract(Container $container): ?CarrierContract;

    public function projectCosts(Container $container, int $daysForward = 30): array;
}
